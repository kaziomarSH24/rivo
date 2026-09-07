<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * @group Payments
 * @subgroup Callbacks
 * Browser-facing success/cancel pages (typically not needed in API docs). Add @hideFromAPIDocumentation if you choose to hide these.
 */
class CallbackController extends Controller
{
    /**
     * Stripe Checkout Session
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function paymentSuccess(Request $request)
    {
        return response('<h1>Payment Successful!</h1><p>Thank you for your purchase. We have received your payment.</p>');
    }

    /**
     *
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function paymentCancel(Request $request)
    {
        return response('<h1>Payment Cancelled</h1><p>Your payment process was cancelled. You have not been charged.</p>');
    }
}
