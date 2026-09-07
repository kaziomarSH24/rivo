<?php

namespace App\Listeners;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Cashier\Events\WebhookReceived;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Stripe\Stripe;
use Stripe\SetupIntent;
use Stripe\PaymentMethod;
use Exception;

class StripeEventListener implements ShouldQueue
{
    /**
     * Handles incoming Stripe webhook events.
     */
    public function handle(WebhookReceived $event): void
    {
        $method = 'handle' . Str::studly(str_replace('.', '_', $event->payload['type']));

        Log::debug("Stripe webhook received. Event: {$event->payload['type']}. Attempting to call method: {$method}");

        if (method_exists($this, $method)) {
            $this->{$method}($event->payload);
        } else {
            Log::info("Method {$method} does not exist in StripeEventListener. Skipping.");
        }
    }

    /**
     * Handles subscription creation idempotently.
     */
    protected function handleCustomerSubscriptionCreated(array $payload): void
    {
        $data = $payload['data']['object'];
        $user = $this->getUserFromPayload($payload);

        if ($user) {
            Subscription::updateOrCreate(
                ['stripe_id' => $data['id']],
                [
                    'user_id' => $user->id,
                    'type' => $data['items']['data'][0]['price']['nickname'] ?? 'default',
                    'stripe_price' => $data['items']['data'][0]['price']['id'],
                    'quantity' => $data['items']['data'][0]['quantity'],
                    'stripe_status' => $data['status'],
                    'trial_ends_at' => isset($data['trial_end']) ? Carbon::createFromTimestamp($data['trial_end']) : null,
                    'ends_at' => $data['cancel_at_period_end'] ? Carbon::createFromTimestamp($data['current_period_end']) : null,
                ]
            );
        }
    }

    /**
     * Handles subscription updates to keep local state in sync.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): void
    {
        $data = $payload['data']['object'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if ($subscription) {
            $subscription->stripe_status = $data['status'];
            $subscription->trial_ends_at = isset($data['trial_end']) ? Carbon::createFromTimestamp($data['trial_end']) : $subscription->trial_ends_at;
            $subscription->ends_at = $data['cancel_at_period_end'] ? Carbon::createFromTimestamp($data['current_period_end']) : null;
            $subscription->save();
        }
    }

    /**
     * Handles 'invoice.payment_succeeded' and 'charge.succeeded' events.
     */
    protected function handleInvoicePaymentSucceeded(array $payload): void
    {
        $this->_processSuccessfulPayment($payload);
    }

    protected function handleChargeSucceeded(array $payload): void
    {
        $this->_processSuccessfulPayment($payload);
    }

    /**
     * Private helper method to process successful payments.
     */
    private function _processSuccessfulPayment(array $payload): void
    {
        $data = $payload['data']['object'];
        $user = $this->getUserFromPayload($payload);

        if (!$user) return;

        $payableId = null;
        $payableType = null;

        if (isset($data['subscription']) && !is_null($data['subscription'])) {
            $subscription = Subscription::where('stripe_id', $data['subscription'])->first();
            if ($subscription) {
                $payableId = $subscription->id;
                $payableType = Subscription::class;
            }
        } else {
            $metadata = $data['metadata'] ?? [];
            $payableId = $metadata['payable_id'] ?? null;
            $payableType = $metadata['payable_type'] ?? null;
        }

        if ($payableId && $payableType && class_exists($payableType)) {
            $lineItem = $data['lines']['data'][0] ?? null;

            $curatedMetadata = [
                'invoice_id' => $data['invoice'] ?? null,
                'charge_id' => $data['charge'] ?? $data['id'] ?? null,
                'receipt_url' => $data['receipt_url'] ?? null,
                'billing_reason' => $data['billing_reason'] ?? 'manual',
                'plan_description' => $lineItem['description'] ?? null,
                'billing_period_start' => isset($lineItem['period']['start']) ? Carbon::createFromTimestamp($lineItem['period']['start'])->toIso8601String() : null,
                'billing_period_end' => isset($lineItem['period']['end']) ? Carbon::createFromTimestamp($lineItem['period']['end'])->toIso8601String() : null,
                'hosted_invoice_url' => $data['hosted_invoice_url'] ?? null,
                'invoice_pdf' => $data['invoice_pdf'] ?? null,
            ];

            Transaction::updateOrCreate(
                ['gateway_transaction_id' => $data['id']],
                [
                    'user_id' => $user->id,
                    'payable_id' => $payableId,
                    'payable_type' => $payableType,
                    'gateway' => 'stripe',
                    'payment_intent_id' => $data['payment_intent'] ?? null,
                    'amount' => ($data['amount_paid'] ?? $data['amount_received'] ?? $data['amount']) / 100,
                    'currency' => $data['currency'],
                    'status' => 'success',
                    'metadata' => $curatedMetadata,
                ]
            );
            Log::info('Transaction successfully created/updated.', ['gateway_transaction_id' => $data['id']]);
        } else {
            Log::warning('Skipping transaction creation. Conditions not met.', [
                'payableId_exists' => !is_null($payableId),
                'payableType_exists' => !is_null($payableType),
            ]);
        }
    }

    /**
     * Best Practice: Handle the 'refund.created' event as the source of truth for refunds.
     */
    protected function handleRefundCreated(array $payload): void
    {
        $refund = $payload['data']['object']; // The payload IS the refund object
        $paymentIntentId = $refund['payment_intent'];
        $chargeId = $refund['charge'];

        // Find the transaction using payment_intent_id or fallback to charge_id
        $transaction = Transaction::where('payment_intent_id', $paymentIntentId)->first();
        if (!$transaction) {
            Log::info('Could not find transaction by payment_intent_id, attempting fallback with charge_id.', ['payment_intent_id' => $paymentIntentId]);
            $transaction = Transaction::where('gateway_transaction_id', $chargeId)->first();
        }

        if ($transaction) {
            $newMetadata = $transaction->metadata;
            $newMetadata['refund_id'] = $refund['id']; // re_...
            $newMetadata['refunded_at'] = Carbon::createFromTimestamp($refund['created'])->toIso8601String();
            $newMetadata['refunded_amount'] = $refund['amount'] / 100;
            $newMetadata['refund_reason'] = $refund['reason'];

            $transaction->metadata = $newMetadata;

            // Update status based on the refund status from the refund object
            if ($refund['status'] === 'succeeded') {
                // You may need to fetch the charge to check if it's partially or fully refunded
                $transaction->status = 'refunded'; // Or implement partial refund logic
            }

            $transaction->save();
            Log::info('Transaction updated with refund details.', ['transaction_id' => $transaction->id, 'refund_id' => $refund['id']]);
        } else {
            Log::warning('Could not find a matching transaction to update with refund details.', [
                'payment_intent_id' => $paymentIntentId,
                'charge_id' => $chargeId,
            ]);
        }
    }

    /**
     * This method can be kept as a simple fallback or for logging purposes,
     * but the main logic is now in handleRefundCreated.
     */
    protected function handleChargeRefunded(array $payload): void
    {
        Log::info('Charge refunded event received. Details are handled by refund.created.', ['charge_id' => $payload['data']['object']['id']]);
        // Optional: You could still update status here as a fallback if needed.
        $charge = $payload['data']['object'];
        $transaction = Transaction::where('payment_intent_id', $charge['payment_intent'])->first();
        if ($transaction && $transaction->status !== 'refunded') {
            $transaction->status = $charge['refunded'] ? 'refunded' : 'partially_refunded';
            $transaction->save();
        }
    }

    /**
     * Handles subscription deletion events.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): void
    {
        $user = $this->getUserFromPayload($payload);
        $subscriptionData = $payload['data']['object'];

        if ($user) {
            activity()
                ->causedBy($user)
                ->withProperty('stripe_subscription_id', $subscriptionData['id'])
                ->log('Subscription was cancelled via Stripe webhook.');
        }
    }

    /**
     * Helper method to retrieve User model from payload.
     */
    private function getUserFromPayload(array $payload): ?User
    {
        $customerId = $payload['data']['object']['customer'] ?? null;
        if (!$customerId) {
            return null;
        }
        return User::where('stripe_id', $customerId)->first();
    }


    protected function handleCheckoutSessionCompleted(array $payload): void
    {
        $session = $payload['data']['object'];

        // We only care about sessions in 'setup' mode here.
        if ($session['mode'] !== 'setup') {
            return;
        }

        try {
            $user = User::where('stripe_id', $session['customer'])->first();
            if (!$user) {
                Log::warning('User not found for completed setup session.', ['customer_id' => $session['customer']]);
                return;
            }

            Stripe::setApiKey(config('cashier.secret'));
            $setupIntent = SetupIntent::retrieve($session['setup_intent']);
            $newPaymentMethodId = $setupIntent->payment_method;

            // Best Practice: Check for duplicate cards using fingerprint.
            $newPaymentMethod = PaymentMethod::retrieve($newPaymentMethodId);
            $newCardFingerprint = $newPaymentMethod->card->fingerprint;

            $existingPaymentMethods = $user->paymentMethods();
            foreach ($existingPaymentMethods as $existingMethod) {
                if ($existingMethod->card->fingerprint === $newCardFingerprint) {
                    Log::info('Duplicate payment method detected via setup session. Skipping.', [
                        'user_id' => $user->id,
                        'fingerprint' => $newCardFingerprint,
                    ]);
                    // If a duplicate is found, we simply do nothing.
                    return;
                }
            }

            // If no duplicate was found, add the new payment method.
            $user->addPaymentMethod($newPaymentMethodId);

            // If the user had no default payment method, this new one becomes the default.
            if (!$user->hasDefaultPaymentMethod()) {
                $user->updateDefaultPaymentMethod($newPaymentMethodId);
            }

            Log::info('New payment method from setup session added successfully.', [
                'user_id' => $user->id,
                'payment_method_id' => $newPaymentMethodId
            ]);
        } catch (Exception $e) {
            Log::error('Error handling checkout.session.completed for setup mode.', [
                'session_id' => $session['id'],
                'error' => $e->getMessage()
            ]);
        }
    }
}
