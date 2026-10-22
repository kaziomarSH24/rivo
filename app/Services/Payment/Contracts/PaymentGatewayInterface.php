<?php

namespace App\Services\Payment\Contracts;

use App\Models\Order;
use App\Services\Payment\DTO\PaymentResult;

interface PaymentGatewayInterface
{
    /**
     * Get unique identifier for this gateway (e.g., 'stripe', 'cash_on_delivery').
     */
    public function getIdentifier(): string;

    /**
     * Process or initialize payment for an order.
     *
     * @param Order $order
     * @param array $options Additional options (e.g. return_url, payment_method_id)
     * @return PaymentResult
     */
    public function processPayment(Order $order, array $options = []): PaymentResult;

    /**
     * Verify / confirm payment from client or webhook payload.
     *
     * @param Order $order
     * @param array $payload Additional verification payload (e.g. payment_intent_id)
     * @return bool
     */
    public function verifyPayment(Order $order, array $payload = []): bool;

    /**
     * Process refund for an order.
     *
     * @param Order $order
     * @param float|null $amount Specific amount to refund, or null for full total
     * @param string|null $reason Reason for refund
     * @return array
     */
    public function refund(Order $order, ?float $amount = null, ?string $reason = null): array;
}