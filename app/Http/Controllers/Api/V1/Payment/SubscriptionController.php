<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @group Payments
 * @subgroup Subscriptions
 * Manage user subscription lifecycle (create, show, cancel, resume, swap).
 */
class SubscriptionController extends Controller
{
    /**
     * Create a new subscription checkout session for the user.
     */
    public function createSubscription(Request $request)
    {
        $request->validate(['price_id' => 'required|string']);

        $user = $request->user();
        $subscription = $user->subscription('default');

        if ($subscription && !$subscription->onGracePeriod()) {
            return response_error('You already have an active subscription.', [], 409);
        }

        if ($subscription?->onGracePeriod()) {
            $subscription->cancelNow();
        }
        $checkoutSession = $user->newSubscription('default', $request->price_id)
            ->trialDays(14)
            ->checkout([
                'success_url' => config('app.frontend_url') . '/dashboard?subscription=success',
                'cancel_url' => config('app.frontend_url') . '/billing?subscription=cancelled',
            ]);

        return response_success('Subscription session created.', ['redirect_url' => $checkoutSession->url]);
    }

    /**
     * Get details of the user's active subscription.
     */
    public function showSubscription(Request $request)
    {
        $subscription = $request->user()->subscription('default');

        if (!$subscription) {
            return response_error('No active subscription found.', [], 404);
        }

        return response_success('Subscription details fetched.', $subscription);
    }

    /**
     * Cancel the user's active subscription at the end of the billing period.
     */
    public function cancelSubscription(Request $request)
    {
        $subscription = $request->user()->subscription('default');

        // Check if the subscription can be cancelled.
        if ($subscription && !$subscription->canceled()) {
            $subscription->cancel();
            return response_success('Subscription cancelled successfully. You can use our service until the end of the billing period.');
        }

        return response_error('Could not cancel subscription. It might already be cancelled or does not exist.', [], 422);
    }

    /**
     * Resume a cancelled subscription if it's still in its grace period.
     */
    public function resumeSubscription(Request $request)
    {
        $subscription = $request->user()->subscription('default');

        // Best Practice: Check if the subscription is resumable.
        if ($subscription && $subscription->onGracePeriod()) {
            $subscription->resume();
            return response_success('Subscription resumed successfully.');
        }

        return response_error('Subscription cannot be resumed. It might not be in a grace period.', [], 422);
    }

    /**
     * Swap the user's subscription to a different plan.
     */
    public function swapPlan(Request $request)
    {
        $request->validate(['new_price_id' => 'required|string']);

        $subscription = $request->user()->subscription('default');

        // Best Practice: Check if the new plan is different and subscription is active.
        if ($subscription && !$subscription->cancelled() && $subscription->stripe_price !== $request->new_price_id) {
            try {
                // The swap method handles proration automatically.
                $subscription->swap($request->new_price_id);
                return response_success('Subscription plan changed successfully.');
            } catch (\Exception $e) {
                // Catch potential Stripe errors (e.g., payment failure for proration).
                return response_error('Could not swap plan.', ['error' => $e->getMessage()], 422);
            }
        }

        return response_error('You are already on this plan or no active subscription found.', [], 422);
    }
}
