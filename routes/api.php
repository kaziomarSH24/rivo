<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\Chat\ConversationController;
use App\Http\Controllers\Api\V1\Chat\GroupController;
use App\Http\Controllers\Api\V1\Chat\MessageController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\Pet\PetController;
use App\Http\Controllers\Api\V1\Walk\WalkController;
use App\Http\Controllers\Api\V1\Walk\WalkRouteController;
use App\Http\Controllers\Api\V1\Payment\InvoiceController;
use App\Http\Controllers\Api\V1\Payment\OneTimePaymentController;
use App\Http\Controllers\Api\V1\Payment\PaymentMethodController;
use App\Http\Controllers\Api\V1\Payment\RefundController;
use App\Http\Controllers\Api\V1\Payment\StripePortalController;
use App\Http\Controllers\Api\V1\Payment\SubscriptionController as StripeSubscriptionController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\Webhook\RevenueCatWebhookController;
use App\Http\Controllers\Api\V1\Ai\AiController;
use App\Http\Controllers\Api\V1\EmergencyContact\EmergencyContactController;
use App\Http\Controllers\Api\V1\Appointment\AppointmentController;

// ==========================================
// PUBLIC ROUTES
// ==========================================

// Auth Routes
Route::prefix('v1/auth')->name('api.v1.auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/social-login', [AuthController::class, 'socialLogin'])->name('socialLogin');
    Route::post('/verify', [VerificationController::class, 'verify'])->name('verify');
    Route::post('/resend-verification', [VerificationController::class, 'resendVerification'])->name('resendVerification');
    Route::post('/forgot-password', [PasswordController::class, 'forgotPassword'])->name('forgotPassword');
    Route::post('/verify-password-otp', [PasswordController::class, 'verifyResetOtp'])->name('verifyResetOtp');
    Route::post('/reset-password-with-token', [PasswordController::class, 'resetPasswordWithToken'])->name('resetPasswordWithToken');
});

// Webhooks
Route::post('v1/webhooks/revenuecat', [RevenueCatWebhookController::class, 'handle']);


// ==========================================
// PROTECTED ROUTES (Requires Authentication)
// ==========================================
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->name('api.v1.')->group(function () {

    // --- User & Profile ---
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/update-password', [PasswordController::class, 'updatePassword'])->name('updatePassword');
    });

    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/me', [ProfileController::class, 'me'])->name('me');
        Route::post('/update', [ProfileController::class, 'updateProfile'])->name('update');
    });

    // --- Core Features ---
    Route::apiResource('pets', PetController::class);
    
    // Appointments
    Route::apiResource('appointments', AppointmentController::class)->except(['show']);
    Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
    Route::get('appointments/{appointment}/notes', [AppointmentController::class, 'getNotes']);
    Route::post('appointments/{appointment}/notes', [AppointmentController::class, 'storeNote']);

    // Emergency Contacts
    Route::apiResource('emergency-contacts', EmergencyContactController::class)->except(['show']);

    // --- Walks Module ---
    Route::prefix('pets/{pet}')->group(function () {
        Route::get('walks', [WalkController::class, 'index']);
        Route::post('walks', [WalkController::class, 'store']);
        Route::get('walk-statistics', [WalkController::class, 'statistics']);
        Route::get('walk-routes-tab', [WalkController::class, 'routesTab']);
        Route::get('walk-stats', [WalkController::class, 'getStats']);
        Route::get('walk-rewards', [WalkController::class, 'rewards']);
    });
    Route::get('walks/{walk}', [WalkController::class, 'show']);
    
    Route::apiResource('walk-routes', WalkRouteController::class)->except(['update', 'show']);
    Route::put('walk-routes/{walk_route}/toggle-favorite', [WalkRouteController::class, 'toggleFavorite']);

    // --- RivoCare AI ---
    Route::prefix('ai')->name('ai.')->group(function () {
        Route::get('appointment-insights', [AiController::class, 'appointmentInsights']);
        
        // Pro Features
        Route::middleware('pro')->group(function () {
            Route::post('chat', [AiController::class, 'chat']);
        });
    });

    // --- Subscriptions (Mobile IAP) ---
    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
        Route::get('plans', [SubscriptionController::class, 'getPlans']);
        Route::get('status', [SubscriptionController::class, 'getStatus']);
    });

    // --- Chat Module ---
    Route::prefix('chat')->name('chat.')->group(function () {
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

    // --- Payments (Stripe Boilerplate) ---
    Route::prefix('payment')->name('payment.')->group(function () {
        Route::prefix('one-time')->name('one-time.')->group(function () {
            Route::post('/checkout-session', [OneTimePaymentController::class, 'createCheckoutSession'])->name('checkout-session');
            Route::post('/payment-intent', [OneTimePaymentController::class, 'createPaymentIntent'])->name('payment-intent');
        });
        Route::prefix('subscriptions')->name('stripe-subscriptions.')->group(function () {
            Route::post('/', [StripeSubscriptionController::class, 'createSubscription'])->name('create');
            Route::get('/', [StripeSubscriptionController::class, 'showSubscription'])->name('show');
            Route::post('/cancel', [StripeSubscriptionController::class, 'cancelSubscription'])->name('cancel');
            Route::post('/resume', [StripeSubscriptionController::class, 'resumeSubscription'])->name('resume');
            Route::post('/swap', [StripeSubscriptionController::class, 'swapPlan'])->name('swap');
        });
        Route::prefix('refunds')->name('refunds.')->group(function () {
            Route::post('/', [RefundController::class, 'requestRefund'])->name('request');
        });
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->name('index');
            Route::get('/{invoice}/download', [InvoiceController::class, 'download'])->name('download');
        });
        Route::prefix('payment-methods')->name('payment-methods.')->group(function () {
            Route::get('/', [PaymentMethodController::class, 'index'])->name('index');
            Route::post('/', [PaymentMethodController::class, 'store'])->name('store');
            Route::patch('/{paymentMethod}/set-default', [PaymentMethodController::class, 'setDefault'])->name('set-default');
            Route::delete('/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('destroy');
            Route::delete('/', [PaymentMethodController::class, 'destroyAll'])->name('destroy-all');
            Route::post('/setup-intent', [PaymentMethodController::class, 'createSetupIntent'])->name('setup-intent');
            Route::post('/setup-session', [PaymentMethodController::class, 'createSetupSession'])->name('setup-session');
        });
        Route::post('/billing-portal', [StripePortalController::class, 'redirectToPortal'])->name('billing-portal');
    });

    // --- Notifications ---
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/stats', [NotificationController::class, 'stats'])->name('stats');
        Route::post('/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
        Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-as-read');
        Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
    });

});

// ==========================================
// FALLBACK ROUTE (Must be at the end)
// ==========================================
Route::fallback(function () {
    return response_error('The requested API endpoint does not exist.', [], 404);
});
