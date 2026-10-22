<?php

namespace App\Services\Payment\Gateways;

use App\Models\Order;
use App\Models\Transaction;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\DTO\PaymentResult;

class CashOnDeliveryGateway implements PaymentGatewayInterface
{
    public function getIdentifier(): string
    {
        return 'cash_on_delivery';
    }

    public function processPayment(Order $order, array $options = []): PaymentResult
    {
        $order->update([
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'pending',
        ]);

        Transaction::updateOrCreate(
            [
                'payable_id' => $order->id,
                'payable_type' => Order::class,
                'gateway' => 'cash_on_delivery',
            ],
            [
                'user_id' => $order->user_id,
                'amount' => $order->total,
                'currency' => 'usd',
                'status' => 'pending',
                'metadata' => [
                    'order_number' => $order->order_number,
                    'note' => 'Payment due on delivery in cash.',
                ],
            ]
        );

        return PaymentResult::pending(
            gateway: 'cash_on_delivery',
            transactionId: 'COD-' . $order->order_number,
            data: [
                'status' => 'pending',
            ],
            message: 'Cash on delivery accepted. Payment will be collected upon delivery.'
        );
    }

    public function verifyPayment(Order $order, array $payload = []): bool
    {
        $order->update([
            'payment_status' => 'paid',
        ]);

        $transaction = Transaction::where('payable_id', $order->id)
            ->where('payable_type', Order::class)
            ->where('gateway', 'cash_on_delivery')
            ->first();

        if ($transaction) {
            $transaction->update(['status' => 'success']);
        }

        return true;
    }

    public function refund(Order $order, ?float $amount = null, ?string $reason = null): array
    {
        $order->update([
            'payment_status' => 'refunded',
        ]);

        $transaction = Transaction::where('payable_id', $order->id)
            ->where('payable_type', Order::class)
            ->first();

        if ($transaction) {
            $transaction->update(['status' => 'refunded']);
        }

        return [
            'success' => true,
            'message' => 'Cash on delivery order cancelled/refunded.',
        ];
    }
}