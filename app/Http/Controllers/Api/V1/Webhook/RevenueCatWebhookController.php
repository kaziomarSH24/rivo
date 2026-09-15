<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RevenueCatWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from RevenueCat.
     */
    public function handle(\Illuminate\Http\Request $request)
    {
        // For security, you should verify the authorization header matches a secret token
        // $token = env('REVENUECAT_WEBHOOK_AUTH_TOKEN');
        // if ($request->header('Authorization') !== "Bearer {$token}") {
        //     return response()->json(['message' => 'Unauthorized'], 401);
        // }

        $event = $request->input('event');

        if (!$event) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $eventType = $event['type'] ?? null;
        $appUserId = $event['app_user_id'] ?? null;
        
        // This is where you would process INITIAL_PURCHASE, RENEWAL, CANCELLATION, etc.
        // And update the user's plan_id in the database.
        
        // \Log::info('RevenueCat Webhook Received', ['type' => $eventType, 'user' => $appUserId]);

        return response()->json(['ok' => true]);
    }
}

