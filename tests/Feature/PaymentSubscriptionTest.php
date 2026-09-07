<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Checkout; // Import the Checkout class
use Laravel\Cashier\Events\WebhookReceived;
use Mockery; // Import Mockery
use Tests\TestCase;

class PaymentSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Best Practice: Test that an authenticated user can create a subscription checkout session.
     * This test mocks Cashier's Checkout class to prevent real Stripe API calls.
     */
    public function test_user_can_create_subscription_session(): void
    {
        // 1. Arrange: Prepare the environment for the test.
        $user = User::factory()->create();

        // Best Practice: Mock the static 'create' method on the Checkout class.
        // This is the most reliable way to prevent real API calls from Cashier's checkout process.
        $this->mock('alias:' . Checkout::class, function (Mockery\MockInterface $mock) {
            // Best Practice: Expect the full chain of methods called by Cashier.
            // First, it sets the customer and returns itself to allow chaining.
            $mock->shouldReceive('customer')->once()->andReturnSelf();

            // Then, it creates the checkout session.
            $mock->shouldReceive('create')->once()->andReturn(
                (object)['url' => 'https://checkout.stripe.com/mock-url']
            );
        });

        // 2. Act: Perform the action we want to test.
        // We simulate a logged-in user making a POST request to our API.
        $response = $this->actingAs($user)->postJson('/api/v1/payment/subscriptions', [
            'price_id' => 'price_does_not_matter_now', // The price ID is irrelevant now
        ]);

        // 3. Assert: Check if the outcome is what we expected.
        $response
            ->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'message' => 'Subscription session created.',
                'data' => [
                    'redirect_url' => 'https://checkout.stripe.com/mock-url'
                ]
            ]);
    }

    /**
     * Best Practice: Test that our webhook listener correctly creates a transaction
     * after receiving a successful payment event from Stripe.
     */
    public function test_webhook_creates_transaction_on_payment_success(): void
    {
        // 1. Arrange: Create a user and a subscription record.
        $user = User::factory()->create([
            'stripe_id' => 'cus_test_customer123',
        ]);
        $subscription = $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_subscription123',
            'stripe_price' => 'price_some_fake_id',
            'stripe_status' => 'active',
            'quantity' => 1,
        ]);

        // This is a simplified, fake webhook payload from Stripe.
        $webhookPayload = [
            'id' => 'evt_test_event123',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_test_invoice123',
                    'customer' => 'cus_test_customer123',
                    'subscription' => 'sub_test_subscription123',
                    'amount_paid' => 5000, // 50.00 USD
                    'currency' => 'usd',
                    'payment_intent' => 'pi_test_paymentintent123',
                    'lines' => ['data' => []], // Keep it simple for the test
                ],
            ],
        ];

        // 2. Act: Dispatch the event that our listener is waiting for.
        WebhookReceived::dispatch($webhookPayload);

        // 3. Assert: Check the database to see if the listener did its job.
        // We check if a new row was created in the 'transactions' table with the correct data.
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id, // FIX: Corrected syntax error (removed extra =>)
            'payable_id' => $subscription->id,
            'payable_type' => \Laravel\Cashier\Subscription::class,
            'gateway_transaction_id' => 'in_test_invoice123',
            'payment_intent_id' => 'pi_test_paymentintent123',
            'amount' => 50.00,
            'status' => 'success',
        ]);
    }
}

