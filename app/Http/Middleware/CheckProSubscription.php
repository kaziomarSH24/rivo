<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckProSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Plan ID 1 is Free. Anything greater than 1 is a premium plan (Plus or Pro).
        // Also check if subscription_expires_at is valid, unless we assume a one-time lifetime purchase.
        // For testing, if plan_id > 1, we allow it.
        if ($user->plan_id > 1) {
            // If they have an expiration date, make sure it's in the future
            if ($user->subscription_expires_at && now()->greaterThan($user->subscription_expires_at)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Your subscription has expired. Please renew to access this feature.'
                ], 403);
            }
            return $next($request);
        }

        return response()->json([
            'ok' => false,
            'message' => 'This feature is only available for Pro users. Please upgrade your plan.'
        ], 403);
    }
}

