<?php

use App\Http\Controllers\Api\V1\Payment\CallbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

//stripe card save test
Route::get('/card', function () {
    return view('stripe.savecardtest');
});

Route::post(
    '/stripe/webhook',
    '\Laravel\Cashier\Http\Controllers\WebhookController@handleWebhook'
);

Route::get('/payment/success', [CallbackController::class, 'paymentSuccess'])->name('payment.success');
Route::get('/payment/cancel', [CallbackController::class, 'paymentCancel'])->name('payment.cancel');

//create a test redirect route for catch parameters
Route::get('/lander', function (Request $request) {
    return response_success('Lander route hit successfully.', $request->all());
})->name('lander');



// Marketplace Live Stripe Checkout & Payment Test
Route::get('/marketplace/checkout-test', function () {
    if (app()->environment('production')) {
        abort(404);
    }
    $user = \App\Models\User::first();
    $token = $user ? $user->createToken('test-blade-token')->plainTextToken : null;
    $cart = \App\Models\Cart::where('user_id', $user?->id)->with('items.product')->first();

    // Ensure cart has at least 1 item for testing
    if ($user && (!$cart || $cart->items->isEmpty())) {
        $product = \App\Models\Product::first();
        if ($product) {
            $cartService = app(\App\Services\Marketplace\CartService::class);
            $cartService->addItem($user, $product->id, null, 1);
            $cart = \App\Models\Cart::where('user_id', $user->id)->with('items.product')->first();
        }
    }

    $products = \App\Models\Product::with(['store', 'variants'])->where('is_active', true)->take(25)->get();
    $addresses = $user ? \App\Models\UserAddress::where('user_id', $user->id)->get() : collect();
    $stripeKey = config('cashier.key');

    return view('marketplace.checkout_test', compact('user', 'token', 'cart', 'products', 'addresses', 'stripeKey'));
});
