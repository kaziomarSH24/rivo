<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * @group Payments
 * @subgroup Billing Portal
 * Access Stripe-hosted customer billing portal.
 */
class StripePortalController extends Controller
{
    /**
     * Create a new billing portal session for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function redirectToPortal(Request $request)
    {
        try {
            // Get the authenticated user
            $user = $request->user();

            // Best Practice: Define a return URL where the user will be sent back
            // after they are done managing their billing on the Stripe portal.
            $returnUrl = config('app.frontend_url') . '/settings/billing';

            // Generate the billing portal session redirect *response object* using Laravel Cashier
            $redirectResponse = $user->redirectToBillingPortal($returnUrl);

            // Best Practice: Extract the actual URL string from the response object for API usage.
            $portalUrl = $redirectResponse->getTargetUrl();

            // Return the URL string to the frontend
            return response_success('Billing portal session created successfully.', [
                'redirect_url' => $portalUrl,
            ]);
        } catch (Exception $e) {
            Log::error('Could not create Stripe billing portal session.', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response_error('Could not access the billing portal. Please try again later.', [], 500);
        }
    }
}

