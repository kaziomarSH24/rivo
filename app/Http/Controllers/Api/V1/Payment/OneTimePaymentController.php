<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
// Only the User model is imported, as the Order model does not exist in this boilerplate
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Laravel\Cashier\Exceptions\IncompletePayment;

/**
 * @group Payments
 * @subgroup One-Time Charges
 * Create one-off payments via PaymentIntent or Checkout Session.
 */
class OneTimePaymentController extends Controller
{
    /**
     * Create a PaymentIntent for use with frontend card elements (e.g., Stripe.js).
     */
    public function createPaymentIntent(Request $request)
    {
        // To support other models in the future, just add their alias to the Rule::in array.
        $validated = $request->validate([
            'amount' => 'required|integer|min:100', // Amount in cents
            'currency' => 'required|string|size:3',
            'payable_type' => ['required', 'string', Rule::in(['user'])], // Only accepts the 'user' alias for now
            'payable_id' => 'required|integer',
            'payment_method_id' => 'required|string',
        ]);

        // Map the alias to the actual model class.
        $classMap = [
            'user' => User::class,
            // Add new models here in the future, e.g., 'order' => \App\Models\Order::class,
        ];
        $payableClass = $classMap[$validated['payable_type']];

        // Dynamically validate that the payable_id exists in the correct table.
        $tableName = (new $payableClass)->getTable();
        $request->validate(['payable_id' => "exists:{$tableName},id"]);

        try {
            $payable = $payableClass::findOrFail($validated['payable_id']);
            $payment = $request->user()->charge(
                $validated['amount'],
                $validated['payment_method_id'],
                [
                    'currency' => strtolower($validated['currency']),
                    'metadata' => [
                        'payable_type' => $payableClass,
                        'payable_id' => $payable->id,
                    ],
                    // Provide a return_url for redirect-based payment methods.
                    'return_url' => config('app.frontend_url') . '/payment/return',
                ]
            );
            return response_success('Payment successful.', [
                'payment' => $payment,
            ]);
        } catch (IncompletePayment $e) {
            // This is triggered if 3D Secure is required.
            // The frontend needs this client_secret to confirm the payment.
            return response_success('Payment requires further action.', [
                'requires_action' => true,
                'client_secret' => $e->payment->client_secret,
            ], 202); // 202 Accepted status is appropriate here.
        } catch (Exception $e) {
            Log::error('Stripe Payment Intent Error: ' . $e->getMessage());
            return response_error('Could not process payment. Please try again later.');
        }
    }

    /**
     * Create a Stripe Checkout Session for one-time payment.
     * Use this to redirect the user to a Stripe-hosted payment page.
     */
    public function createCheckoutSession(Request $request)
    {
        // Validate all necessary data for dynamic price creation.
        $validated = $request->validate([
            'amount' => 'required|integer|min:100',
            'currency' => 'required|string|size:3',
            'description' => 'required|string|max:255',
            'payable_type' => ['required', 'string', Rule::in(['user'])],
            'payable_id' => 'required|integer',
        ]);

        $classMap = [
            'user' => User::class,
            // Add new models here in the future.
        ];
        $payableClass = $classMap[$validated['payable_type']];

        $tableName = (new $payableClass)->getTable();
        $request->validate(['payable_id' => "exists:{$tableName},id"]);

        try {
            $payable = $payableClass::findOrFail($validated['payable_id']);

            // Create a checkout session with dynamically generated price data.
            $checkoutSession = $request->user()->checkout(
                [], // First argument is always empty when using price_data.
                [   // All options, including line_items, go in the second argument.
                    'mode' => 'payment',
                    'success_url' => config('app.frontend_url') . '/payment/success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => config('app.frontend_url') . '/payment/cancelled',
                    'payment_intent_data' => [
                        'metadata' => [
                            'payable_type' => $payableClass,
                            'payable_id'   => $payable->id,
                        ]
                    ],
                    // line_items has been moved inside the second argument.
                    'line_items' => [[
                        'price_data' => [
                            'currency' => strtolower($validated['currency']),
                            'product_data' => [
                                'name' => $validated['description'],
                            ],
                            'unit_amount' => $validated['amount'],
                        ],
                        'quantity' => 1,
                    ]],
                ]
            );

            return response_success('Checkout session created.', ['redirect_url' => $checkoutSession->url]);
        } catch (Exception $e) {
            Log::error('Stripe Checkout Session Error: ' . $e->getMessage());
            return response_error('Could not create payment session. Please try again later.', [], 500);
        }
    }
}
