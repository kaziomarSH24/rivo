<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Stripe;

/**
 * @group Payments
 * @subgroup Payment Methods
 * Manage saved cards & default payment method.
 */
class PaymentMethodController extends Controller
{
    /**
     * Create a Checkout Session in 'setup' mode to generate a shareable link
     * for users to add a payment method on a Stripe-hosted page.
     */
    public function createSetupSession(Request $request) //for save card
    {
        try {
            $checkoutSession = $request->user()->checkout(
                [], // This is empty as we are not creating a charge
                [
                    'mode' => 'setup', // This is the key to creating a setup session
                    'currency' => 'usd',
                    'success_url' => config('app.frontend_url') . '/settings/billing?card_added=true',
                    'cancel_url' => config('app.frontend_url') . '/settings/billing?card_added=false',
                ]
            );

            return response_success('Setup session created successfully.', [
                'redirect_url' => $checkoutSession->url
            ]);
        } catch (Exception $e) {
            Log::error('Could not create setup session.', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);
            return response_error('Could not create a link to add payment method. Please try again.', [], 500);
        }
    }

    /**
     * Create a SetupIntent to securely collect payment method details on the frontend.
     */

    public function createSetupIntent(Request $request)
    {
        try {
            $setupIntent = $request->user()->createSetupIntent();
            return response_success('Setup Intent created successfully.', [
                'client_secret' => $setupIntent->client_secret,
            ]);
        } catch (Exception $e) {
            Log::error('Could not create Setup Intent.', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);
            return response_error('Could not prepare the payment form. Please try again.', [], 500);
        }
    }
    /**
     * Display a list of the user's saved payment methods.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $paymentMethodsData = [];

        if ($user->hasPaymentMethod()) {
            //Get the default payment method once to avoid repeated queries in a loop.
            $defaultPaymentMethod = $user->defaultPaymentMethod();

            $paymentMethods = $user->paymentMethods();

            if (empty($paymentMethods)) {
                return response_success('No payment methods found.', []);
            }

            foreach ($paymentMethods as $method) {
                $paymentMethodsData[] = [
                    'id' => $method->id,
                    'brand' => $method->card->brand,
                    'last_four' => $method->card->last4,
                    'exp_month' => $method->card->exp_month,
                    'exp_year' => $method->card->exp_year,
                    //Compare with the pre-fetched default payment method object.
                    'is_default' => $defaultPaymentMethod && $defaultPaymentMethod->id === $method->id,
                ];
            }
        }

        return response_success('Payment methods fetched successfully.', $paymentMethodsData);
    }

    /**
     * Add a new payment method (e.g., a card) for the user.
     */
    public function store(Request $request)
    {
        $request->validate(['payment_method_id' => 'required|string']);

        $user = $request->user();
        $newPaymentMethodId = $request->payment_method_id;

        try {
            //Check for duplicate cards using fingerprint.
            Stripe::setApiKey(config('cashier.secret'));
            $newPaymentMethod = \Stripe\PaymentMethod::retrieve($newPaymentMethodId);
            $newCardFingerprint = $newPaymentMethod->card->fingerprint;

            $existingPaymentMethods = $user->paymentMethods();
            foreach ($existingPaymentMethods as $existingMethod) {
                if ($existingMethod->card->fingerprint === $newCardFingerprint) {
                    return response_error('This payment method is already saved.', [], 409); // 409 Conflict
                }
            }

            // If no duplicate is found, add the payment method.
            if ($user->hasPaymentMethod()) {
                $user->addPaymentMethod($newPaymentMethodId);
            } else {
                $user->updateDefaultPaymentMethod($newPaymentMethodId);
            }

            return response_success('Payment method added successfully.');
        } catch (IncompletePayment $e) {
            Log::warning('Incomplete payment when adding a new payment method.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response_error(
                'The payment method could not be added. It may require authentication or was declined.',
                ['stripe_error' => $e->getMessage()],
                422
            );
        } catch (Exception $e) {
            Log::error('Could not add payment method.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response_error('Could not add payment method. Please try again.', [], 500);
        }
    }

    /**
     * Set a payment method as the user's default.
     */
    public function setDefault(Request $request, string $paymentMethodId)
    {
        try {
            $request->user()->updateDefaultPaymentMethod($paymentMethodId);
            return response_success('Default payment method updated successfully.');
        } catch (Exception $e) {
            Log::error('Could not set default payment method.', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);
            return response_error('Could not update default payment method. Please try again.', [], 500);
        }
    }

    /**
     * Delete a payment method.
     */
    public function destroy(Request $request, string $paymentMethodId)
    {
        $user = $request->user();
        try {
            $paymentMethodToDelete = $user->findPaymentMethod($paymentMethodId);

            if (!$paymentMethodToDelete) {
                return response_error('Payment method not found.', [], 404);
            }

            //  Use the subscribed() method to check for an active, non-grace-period subscription.
            if ($user->subscribed('default') && $user->paymentMethods()->count() <= 1) {
                return response_error('You cannot delete your only payment method while you have an active subscription.', [], 422);
            }

            $paymentMethodToDelete->delete();

            return response_success('Payment method deleted successfully.');
        } catch (Exception $e) {
            Log::error('Could not delete payment method.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response_error('Could not delete payment method. Please try again.', [], 500);
        }
    }

    /**
     *Delete all payment methods for the user.
     * Includes a safety check to prevent deletion if an active subscription exists.
     */
    public function destroyAll(Request $request)
    {
        $user = $request->user();

        try {
            if ($user->subscribed('default')) {
                return response_error('You cannot delete all payment methods while you have an active subscription.', [], 422);
            }

            $paymentMethods = $user->paymentMethods();

            foreach ($paymentMethods as $paymentMethod) {
                $paymentMethod->delete();
            }

            return response_success('All payment methods have been deleted successfully.');
        } catch (Exception $e) {
            Log::error('Could not delete all payment methods.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response_error('An error occurred while deleting payment methods. Please try again.', [], 500);
        }
    }
}
