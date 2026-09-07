<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Transaction; // Transaction model import korun
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Payments
 * @subgroup Refunds
 * Request refunds for eligible transactions.
 */
class RefundController extends Controller
{
    /**
     * Sends a refund request for a specific transaction.
     */
    public function requestRefund(Request $request)
    {
        //  The API should accept the Payment Intent ID directly.
        $validated = $request->validate([
            'payment_intent_id' => 'required|string|starts_with:pi_',
        ]);

        try {
            // Verify that this payment intent belongs to an existing transaction for the user.
            $transactionExists = Transaction::where('payment_intent_id', $validated['payment_intent_id'])
                                            ->where('user_id', $request->user()->id)
                                            ->exists();

            if (!$transactionExists) {
                return response_error('Transaction not found or you do not have permission to refund it.', [], 404);
            }

            // Use the validated Payment Intent ID to request the refund.
            $request->user()->refund($validated['payment_intent_id']);

            return response_success('Refund requested successfully. It may take a few days to process.');

        } catch (\Exception $e) {
            Log::error('Stripe Refund Error: ' . $e->getMessage());
            return response_error('Refund failed. Please try again later.', ['error' => $e->getMessage()], 422);
        }
    }
}

