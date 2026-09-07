<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\Chat\ConversationController;
use App\Http\Controllers\Api\V1\Chat\GroupController;
use App\Http\Controllers\Api\V1\Chat\MessageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Payment\InvoiceController;
use App\Http\Controllers\Api\V1\Payment\OneTimePaymentController;
use App\Http\Controllers\Api\V1\Payment\PaymentMethodController;
use App\Http\Controllers\Api\V1\Payment\RefundController;
use App\Http\Controllers\Api\V1\Payment\StripePortalController;
use App\Http\Controllers\Api\V1\Payment\SubscriptionController;



// Route::post(
//     '/v1/stripe/webhook',
//     [WebhookController::class, 'handleWebhook']
// )->name('cashier.webhook');

// --- Public Routes (Authentication) ---
Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    Route::post('/verify', [VerificationController::class, 'verify'])->name('api.v1.auth.verify');
    Route::post('/resend-verification', [VerificationController::class, 'resendVerification'])->name('api.v1.auth.resendVerification');

    Route::post('/forgot-password', [PasswordController::class, 'forgotPassword'])->name('api.v1.auth.forgotPassword');
    Route::post('/verify-password-otp', [PasswordController::class, 'verifyResetOtp'])->name('api.v1.auth.verifyResetOtp');
    Route::post('/reset-password-with-token', [PasswordController::class, 'resetPasswordWithToken'])->name('api.v1.auth.resetPasswordWithToken');
});

// Route::post('/upload', [FileController::class, 'handleRequest'])->name('api.v1.file.upload');

// --- Protected Routes (User must be logged in) ---
Route::middleware('auth:sanctum', 'throttle:api')->prefix('v1')->group(function () {

    // Auth related protected routes
    Route::prefix('auth')->name('api.v1.auth.')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/update-password', [PasswordController::class, 'updatePassword'])->name('updatePassword');
    });

    // Profile related protected routes
    Route::prefix('profile')->name('api.v1.profile.')->group(function () {
        Route::get('/me', [ProfileController::class, 'me'])->name('me');
        Route::post('/update', [ProfileController::class, 'updateProfile'])->name('update');
    });

    /**
     ** Chat Module Routes
     */
    Route::prefix('chat')->name('api.v1.chat.')->group(function () {
        // Conversations
        Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');

        // Messages
        Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
        Route::patch('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
        Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
        Route::post('/messages/read', [MessageController::class, 'markAsRead'])->name('messages.read');

        // Group Management
        Route::post('/groups/{conversation}/members', [GroupController::class, 'addMember'])->name('groups.members.add');
        Route::delete('/groups/{conversation}/members', [GroupController::class, 'removeMember'])->name('groups.members.remove');
        Route::post('/groups/{conversation}/leave', [GroupController::class, 'leaveGroup'])->name('groups.leave');
        Route::post('/groups/{conversation}/promote', [GroupController::class, 'promoteToAdmin'])->name('groups.promote');
        Route::post('/groups/{conversation}/demote', [GroupController::class, 'demoteToMember'])->name('groups.demote');

        // Real-time
        Route::post('/conversations/{conversation}/typing', [MessageController::class, 'typing'])->name('typing');
    });


    //**---Payment Method routes---**//
    Route::prefix('payment')->name('api.v1.payment.')->group(function () {

        // One-time payment routes
        Route::prefix('one-time')->name('one-time.')->group(function () {
            Route::post('/checkout-session', [OneTimePaymentController::class, 'createCheckoutSession'])->name('checkout-session');
            Route::post('/payment-intent', [OneTimePaymentController::class, 'createPaymentIntent'])->name('payment-intent');
        });

        // Subscription routes
        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::post('/', [SubscriptionController::class, 'createSubscription'])->name('create');
            Route::get('/', [SubscriptionController::class, 'showSubscription'])->name('show');
            Route::post('/cancel', [SubscriptionController::class, 'cancelSubscription'])->name('cancel');
            Route::post('/resume', [SubscriptionController::class, 'resumeSubscription'])->name('resume');
            Route::post('/swap', [SubscriptionController::class, 'swapPlan'])->name('swap');
        });

        // Refund routes
        Route::prefix('refunds')->name('refunds.')->group(function () {
            Route::post('/', [RefundController::class, 'requestRefund'])->name('request');
        });

        // Invoice routes
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->name('index');
            Route::get('/{invoice}/download', [InvoiceController::class, 'download'])->name('download');
        });

        // Payment method routes
        Route::prefix('payment-methods')->name('payment-methods.')->group(function () {
            Route::get('/', [PaymentMethodController::class, 'index'])->name('index');
            Route::post('/', [PaymentMethodController::class, 'store'])->name('store');
            Route::patch('/{paymentMethod}/set-default', [PaymentMethodController::class, 'setDefault'])->name('set-default');
            Route::delete('/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('destroy');
            Route::delete('/', [PaymentMethodController::class, 'destroyAll'])->name('destroy-all');
            //createSetupIntent
            Route::post('/setup-intent', [PaymentMethodController::class, 'createSetupIntent'])->name('setup-intent');
            //createSetupSession for save card
            Route::post('/setup-session', [PaymentMethodController::class, 'createSetupSession'])->name('setup-session');
        });

        // Stripe billing portal route
        Route::post('/billing-portal', [StripePortalController::class, 'redirectToPortal'])->name('billing-portal');
    });


    //***--- Notification Routes ---***/
    Route::prefix('notifications')->name('api.v1.notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/stats', [NotificationController::class, 'stats'])->name('stats');
        Route::post('/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
        Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-as-read');
        Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
    });

    Route::fallback(function () {
        return response_error('The requested API endpoint does not exist.', [], 404);
    });
});
