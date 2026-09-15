<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Get all available subscription plans.
     */
    public function getPlans()
    {
        $plans = \App\Models\Plan::all();

        return response()->json([
            'ok' => true,
            'data' => $plans
        ]);
    }

    /**
     * Get the current user's active subscription status.
     */
    public function getStatus(Request $request)
    {
        $user = $request->user()->load('plan');

        return response()->json([
            'ok' => true,
            'data' => [
                'plan' => $user->plan,
                'is_pro' => $user->plan_id > 1,
                'expires_at' => $user->subscription_expires_at
            ]
        ]);
    }
}

