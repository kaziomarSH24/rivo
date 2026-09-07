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
