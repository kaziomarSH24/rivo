<?php

namespace App\Services\Payment\Gateways;

use App\Models\Order;
use App\Models\Transaction;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\DTO\PaymentResult;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        $this->initStripeKey();
    }

    protected function initStripeKey(): void
    {
        $secretKey = config('cashier.secret') ?: env('STRIPE_SECRET');
        if ($secretKey && !$this->isDummyKey()) {
            Stripe::setApiKey($secretKey);
        }
    }

    public function isDummyKey(): bool
    {
        $secretKey = config('cashier.secret') ?: env('STRIPE_SECRET');
        return empty($secretKey) || $secretKey === 'sk_test_...' || str_ends_with($secretKey, '...');
    }

    public function getIdentifier(): string
    {
        return 'stripe';
    }

    /**
     * Initialize / charge a Stripe PaymentIntent for the order
     */
    public function processPayment(Order $order, array $options = []): PaymentResult
    {
        // If order total is 0, no payment required
        if ($order->total <= 0) {
            $order->update([
                'payment_method' => 'stripe',
                'payment_status' => 'paid',
            ]);
            return PaymentResult::success('stripe', null, [], 'Free order processed.');
        }

        // Local development sandbox fallback if Stripe secret is placeholder (sk_test_...)
        if ($this->isDummyKey()) {
            $mockIntentId = 'pi_sim_' . Str::random(24);
            $mockClientSecret = $mockIntentId . '_secret_' . Str::random(24);

            $order->update([
                'transaction_id' => $mockIntentId,
                'payment_method' => 'stripe',
                'payment_status' => 'pending',
            ]);

            return PaymentResult::requiresAction(
                gateway: 'stripe',
                transactionId: $mockIntentId,
                clientSecret: $mockClientSecret,
                data: [
                    'status' => 'requires_action',
                    'publishable_key' => config('cashier.key') ?: 'pk_test_simulated',
                    'customer_id' => 'cus_sim_' . $order->user_id,
                    'mode' => 'sandbox_simulated',
                ],
                message: 'Stripe PaymentSheet initialized (Sandbox mode). Complete payment and confirm with /confirm-payment endpoint.'
            );
        }

        try {
            $this->initStripeKey();

            $user = $order->user;
            $stripeCustomerId = null;

            if ($user && method_exists($user, 'createOrGetStripeCustomer')) {
                try {
                    $stripeCustomerId = $user->createOrGetStripeCustomer()->id;
                } catch (Exception $e) {
                    Log::warning('Could not create or get Stripe customer: ' . $e->getMessage());
                }
            }

            $amountCents = (int) round($order->total * 100);
            $currency = strtolower($options['currency'] ?? 'usd');

            $params = [
                'amount' => $amountCents,
                'currency' => $currency,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'user_id' => (string) $order->user_id,
                    'payable_id' => (string) $order->id,
                    'payable_type' => Order::class,
                ],
                'description' => "Order #{$order->order_number} at Rivo Marketplace",
            ];

            if ($stripeCustomerId) {
                $params['customer'] = $stripeCustomerId;
            }

            // If a saved payment method ID was passed from frontend
            if (!empty($options['payment_method_id'])) {
                $params['payment_method'] = $options['payment_method_id'];
                $params['confirm'] = true;
                $params['return_url'] = config('app.frontend_url') . '/payment/return';
            }

            $intent = PaymentIntent::create($params);

            // Record transaction ID on order
            $order->update([
                'transaction_id' => $intent->id,
                'payment_method' => 'stripe',
            ]);

            // If payment succeeded immediately
            if ($intent->status === 'succeeded') {
                $order->update(['payment_status' => 'paid']);
                $this->recordTransaction($order, $intent);

                return PaymentResult::success(
                    gateway: 'stripe',
                    transactionId: $intent->id,
                    data: [
                        'status' => 'succeeded',
                        'publishable_key' => config('cashier.key') ?: env('STRIPE_KEY'),
                    ],
                    message: 'Payment confirmed successfully.'
                );
            }

            // Return client secret for mobile PaymentSheet / Apple Pay Sheet
            return PaymentResult::requiresAction(
                gateway: 'stripe',
                transactionId: $intent->id,
                clientSecret: $intent->client_secret,
                data: [
                    'status' => $intent->status,
                    'publishable_key' => config('cashier.key') ?: env('STRIPE_KEY'),
                    'customer_id' => $stripeCustomerId,
                ],
                message: 'Stripe payment sheet initialized. Awaiting client authorization.'
            );
        } catch (Exception $e) {
            Log::error('Stripe Payment Processing Failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
            ]);

            $order->update(['payment_status' => 'failed']);

            return PaymentResult::failed('stripe', 'Stripe payment error: ' . $e->getMessage());
        }
    }

    /**
     * Verify payment status via Stripe PaymentIntent
     */
    public function verifyPayment(Order $order, array $payload = []): bool
    {
        $intentId = $payload['payment_intent_id'] ?? $order->transaction_id;
        if (!$intentId) {
            return false;
        }

        // Sandbox simulated verification
        if ($this->isDummyKey() || str_starts_with($intentId, 'pi_sim_') || str_starts_with($intentId, 'pi_test_')) {
            $order->update([
                'payment_status' => 'paid',
                'transaction_id' => $intentId,
            ]);

            Transaction::updateOrCreate(
                ['payment_intent_id' => $intentId],
                [
                    'user_id' => $order->user_id,
                    'payable_id' => $order->id,
                    'payable_type' => Order::class,
                    'gateway' => 'stripe',
                    'gateway_transaction_id' => $intentId,
                    'amount' => $order->total,
                    'currency' => 'usd',
                    'status' => 'success',
                    'metadata' => [
                        'order_number' => $order->order_number,
                        'payment_method_type' => 'card',
                        'mode' => 'sandbox_simulated',
                    ],
                ]
            );

            return true;
        }

        try {
            $this->initStripeKey();
            $intent = PaymentIntent::retrieve($intentId);

            if ($intent->status === 'succeeded') {
                $order->update([
                    'payment_status' => 'paid',
                    'transaction_id' => $intent->id,
                ]);

                $this->recordTransaction($order, $intent);

                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Stripe Payment Verification Failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'intent_id' => $intentId,
            ]);
            return false;
        }
    }

    /**
     * Refund an order via Stripe
     */
    public function refund(Order $order, ?float $amount = null, ?string $reason = null): array
    {
        if (!$order->transaction_id) {
            return [
                'success' => false,
                'message' => 'No Stripe transaction ID found for this order.',
            ];
        }

        // Sandbox simulation
        if ($this->isDummyKey() || str_starts_with($order->transaction_id, 'pi_sim_') || str_starts_with($order->transaction_id, 'pi_test_')) {
            $order->update(['payment_status' => 'refunded']);

            $transaction = Transaction::where('payment_intent_id', $order->transaction_id)->first();
            if ($transaction) {
                $meta = $transaction->metadata ?: [];
                $meta['refund_id'] = 're_sim_' . Str::random(20);
                $meta['refund_reason'] = $reason;

                $transaction->update([
                    'status' => 'refunded',
                    'metadata' => $meta,
                ]);
            }

            return [
                'success' => true,
                'refund_id' => 're_sim_' . Str::random(20),
                'amount' => $amount ?: $order->total,
                'status' => 'succeeded',
            ];
        }

        try {
            $this->initStripeKey();

            $refundParams = [
                'payment_intent' => $order->transaction_id,
            ];

            if ($amount && $amount > 0) {
                $refundParams['amount'] = (int) round($amount * 100);
            }

            $refund = Refund::create($refundParams);

            $order->update([
                'payment_status' => 'refunded',
            ]);

            // Update Transaction record
            $transaction = Transaction::where('payment_intent_id', $order->transaction_id)->first();
            if ($transaction) {
                $meta = $transaction->metadata ?: [];
                $meta['refund_id'] = $refund->id;
                $meta['refund_amount'] = ($refund->amount / 100);
                $meta['refund_reason'] = $reason;

                $transaction->update([
                    'status' => 'refunded',
                    'metadata' => $meta,
                ]);
            }

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100,
                'status' => $refund->status,
            ];
        } catch (Exception $e) {
            Log::error('Stripe Refund Failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
            ]);

            return [
                'success' => false,
                'message' => 'Stripe refund failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Record or update Transaction model polymorphic to Order
     */
    protected function recordTransaction(Order $order, PaymentIntent $intent): Transaction
    {
        $charge = !empty($intent->charges->data) ? $intent->charges->data[0] : null;

        return Transaction::updateOrCreate(
            ['payment_intent_id' => $intent->id],
            [
                'user_id' => $order->user_id,
                'payable_id' => $order->id,
                'payable_type' => Order::class,
                'gateway' => 'stripe',
                'gateway_transaction_id' => $charge ? $charge->id : $intent->id,
                'amount' => $order->total,
                'currency' => $intent->currency ?: 'usd',
                'status' => 'success',
                'metadata' => [
                    'order_number' => $order->order_number,
                    'payment_method_type' => !empty($intent->payment_method_types) ? $intent->payment_method_types[0] : 'card',
                    'receipt_url' => $charge ? $charge->receipt_url : null,
                ],
            ]
        );
    }
}
