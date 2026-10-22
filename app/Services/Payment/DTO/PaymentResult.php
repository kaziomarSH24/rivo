<?php

namespace App\Services\Payment\DTO;

class PaymentResult
{
    public function __construct(
        public readonly string $gateway,
        public readonly bool $isSuccessful,
        public readonly bool $requiresAction,
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $clientSecret = null,
        public readonly ?string $message = null,
        public readonly array $data = []
    ) {}

    /**
     * Create a pending / action-required result (e.g. Stripe PaymentIntent ready for client sheet)
     */
    public static function requiresAction(
        string $gateway,
        string $transactionId,
        string $clientSecret,
        array $data = [],
        ?string $message = 'Payment requires client confirmation.'
    ): self {
        return new self(
            gateway: $gateway,
            isSuccessful: false,
            requiresAction: true,
            status: 'requires_action',
            transactionId: $transactionId,
            clientSecret: $clientSecret,
            message: $message,
            data: $data
        );
    }

    /**
     * Create an immediately successful payment result
     */
    public static function success(
        string $gateway,
        ?string $transactionId = null,
        array $data = [],
        ?string $message = 'Payment processed successfully.'
    ): self {
        return new self(
            gateway: $gateway,
            isSuccessful: true,
            requiresAction: false,
            status: 'paid',
            transactionId: $transactionId,
            clientSecret: null,
            message: $message,
            data: $data
        );
    }

    /**
     * Create a pending payment result (e.g. Cash On Delivery)
     */
    public static function pending(
        string $gateway,
        ?string $transactionId = null,
        array $data = [],
        ?string $message = 'Payment is pending (Cash on delivery).'
    ): self {
        return new self(
            gateway: $gateway,
            isSuccessful: false,
            requiresAction: false,
            status: 'pending',
            transactionId: $transactionId,
            clientSecret: null,
            message: $message,
            data: $data
        );
    }

    /**
     * Create a failed payment result
     */
    public static function failed(
        string $gateway,
        string $message = 'Payment failed.',
        array $data = []
    ): self {
        return new self(
            gateway: $gateway,
            isSuccessful: false,
            requiresAction: false,
            status: 'failed',
            transactionId: null,
            clientSecret: null,
            message: $message,
            data: $data
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'gateway' => $this->gateway,
            'is_successful' => $this->isSuccessful,
            'requires_action' => $this->requiresAction,
            'status' => $this->status,
            'transaction_id' => $this->transactionId,
            'client_secret' => $this->clientSecret,
            'message' => $this->message,
            'data' => $this->data,
        ], fn($value) => !is_null($value));
    }
}