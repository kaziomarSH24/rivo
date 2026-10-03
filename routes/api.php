<?php

use App\Http\Controllers\Api\V1\Pet\CareController;
use App\Http\Controllers\Api\V1\Pet\HealthVaultController;
use App\Http\Controllers\Api\V1\Pet\LostPetReportController;
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
Route::prefix('v1/auth')->name('api.v1.auth.')->controller(AuthController::class)->group(function () {
    Route::post('/register', 'register')->name('register');
    Route::post('/login', 'login')->name('login');
    Route::post('/social-login', 'socialLogin')->name('socialLogin');
    Route::post('/logout', 'logout')->name('logout')->middleware('auth:sanctum');
});

Route::prefix('v1/auth')->name('api.v1.auth.')->controller(VerificationController::class)->group(function () {
    Route::post('/verify', 'verify')->name('verify');
    Route::post('/resend-verification', 'resendVerification')->name('resendVerification');
});

Route::prefix('v1/auth')->name('api.v1.auth.')->controller(PasswordController::class)->group(function () {
    Route::post('/forgot-password', 'forgotPassword')->name('forgotPassword');
    Route::post('/verify-password-otp', 'verifyResetOtp')->name('verifyResetOtp');
    Route::post('/reset-password-with-token', 'resetPasswordWithToken')->name('resetPasswordWithToken');
    Route::post('/update-password', 'updatePassword')->name('updatePassword')->middleware('auth:sanctum');
});

// Webhooks
Route::post('v1/webhooks/revenuecat', [RevenueCatWebhookController::class, 'handle']);


// ==========================================
// PROTECTED ROUTES (Requires Authentication)
// ==========================================
Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->name('api.v1.')->group(function () {

    Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
        Route::get('/me', 'me')->name('me');
        Route::post('/update', 'updateProfile')->name('update');
    });

    // --- Core Features ---
    Route::apiResource('pets', PetController::class);

    // --- Care Module ---
    Route::apiResource('pets.care-tasks', CareController::class)->only(['index', 'store']);
    Route::post('pets/{pet}/care-tasks/{careTask}/complete', [CareController::class, 'complete']);

    // --- Health Vault Module ---
    Route::controller(HealthVaultController::class)->prefix('pets/{pet}/health-vault')->group(function () {
        Route::get('dashboard', 'getDashboard');
        Route::get('documents', 'getDocuments');
        Route::post('documents', 'uploadDocument');
        Route::delete('documents/{document}', 'deleteDocument');
        Route::get('weight', 'getWeightLogs');
        Route::post('weight', 'logWeight');
    });
    
    // --- Appointments ---
    Route::apiResource('appointments', AppointmentController::class)->except(['show']);
    Route::controller(AppointmentController::class)->prefix('appointments/{appointment}')->group(function () {
        Route::patch('status', 'updateStatus');
        Route::get('notes', 'getNotes');
        Route::post('notes', 'storeNote');
    });

    // --- Emergency Contacts ---
    Route::apiResource('emergency-contacts', EmergencyContactController::class)->except(['show']);

    // --- Walks Module ---
    Route::controller(WalkController::class)->prefix('pets/{pet}')->group(function () {
        Route::get('walks', 'index');
        Route::post('walks', 'store');
        Route::get('walk-statistics', 'statistics');
        Route::get('walk-routes-tab', 'routesTab');
        Route::get('walk-stats', 'getStats');
        Route::get('walk-rewards', 'rewards');
    });
    Route::get('walks/{walk}', [WalkController::class, 'show']);
    
    Route::apiResource('walk-routes', WalkRouteController::class)->except(['update', 'show']);
    Route::put('walk-routes/{walk_route}/toggle-favorite', [WalkRouteController::class, 'toggleFavorite']);

    // --- RivoCare AI ---
    Route::controller(AiController::class)->prefix('ai')->name('ai.')->group(function () {
        Route::get('appointment-insights', 'appointmentInsights');
        Route::middleware('pro')->post('chat', 'chat');
    });

    // --- Subscriptions (Mobile IAP) ---
    Route::controller(SubscriptionController::class)->prefix('subscriptions')->name('subscriptions.')->group(function () {
        Route::get('plans', 'getPlans');
        Route::get('status', 'getStatus');
    });

    // --- Chat Module ---
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::controller(ConversationController::class)->prefix('conversations')->group(function () {
            Route::get('/', 'index')->name('conversations.index');
            Route::post('/', 'store')->name('conversations.store');
        });

        Route::controller(MessageController::class)->group(function () {
            Route::get('conversations/{conversation}/messages', 'index')->name('messages.index');
            Route::post('messages', 'store')->name('messages.store');
            Route::patch('messages/{message}', 'update')->name('messages.update');
            Route::delete('messages/{message}', 'destroy')->name('messages.destroy');
            Route::post('messages/read', 'markAsRead')->name('messages.read');
            Route::post('conversations/{conversation}/typing', 'typing')->name('typing');
        });

        Route::controller(GroupController::class)->prefix('groups/{conversation}')->name('groups.')->group(function () {
            Route::post('members', 'addMember')->name('members.add');
            Route::delete('members', 'removeMember')->name('members.remove');
            Route::post('leave', 'leaveGroup')->name('leave');
            Route::post('promote', 'promoteToAdmin')->name('promote');
            Route::post('demote', 'demoteToMember')->name('demote');
        });
    });

    // --- Payments (Stripe Boilerplate) ---
    Route::prefix('payment')->name('payment.')->group(function () {
        Route::controller(OneTimePaymentController::class)->prefix('one-time')->name('one-time.')->group(function () {
            Route::post('/checkout-session', 'createCheckoutSession')->name('checkout-session');
            Route::post('/payment-intent', 'createPaymentIntent')->name('payment-intent');
        });
        
        Route::controller(StripeSubscriptionController::class)->prefix('subscriptions')->name('stripe-subscriptions.')->group(function () {
            Route::post('/', 'createSubscription')->name('create');
            Route::get('/', 'showSubscription')->name('show');
            Route::post('/cancel', 'cancelSubscription')->name('cancel');
            Route::post('/resume', 'resumeSubscription')->name('resume');
            Route::post('/swap', 'swapPlan')->name('swap');
        });
        
        Route::post('refunds', [RefundController::class, 'requestRefund'])->name('refunds.request');
        
        Route::controller(InvoiceController::class)->prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{invoice}/download', 'download')->name('download');
        });
        
        Route::controller(PaymentMethodController::class)->prefix('payment-methods')->name('payment-methods.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::patch('/{paymentMethod}/set-default', 'setDefault')->name('set-default');
            Route::delete('/{paymentMethod}', 'destroy')->name('destroy');
            Route::delete('/', 'destroyAll')->name('destroy-all');
            Route::post('/setup-intent', 'createSetupIntent')->name('setup-intent');
            Route::post('/setup-session', 'createSetupSession')->name('setup-session');
        });
        
        Route::post('/billing-portal', [StripePortalController::class, 'redirectToPortal'])->name('billing-portal');
    });

    // --- Notifications ---
    Route::controller(NotificationController::class)->prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/stats', 'stats')->name('stats');
        Route::post('/{notification}/mark-as-read', 'markAsRead')->name('mark-as-read');
        Route::post('/mark-all-as-read', 'markAllAsRead')->name('mark-all-as-read');
        Route::delete('/{notification}', 'destroy')->name('destroy');
    });

    // ==========================================
    // LOST MODE & COMMUNITY
    // ==========================================
    Route::controller(LostPetReportController::class)->group(function () {
        Route::prefix('pets/{pet}/lost-reports')->group(function () {
            Route::get('active', 'getActive');
            Route::post('/', 'store');
            Route::post('post-to-community', 'postToCommunity');
            Route::post('mark-found', 'markAsFound');
        });
        
        Route::get('community/lost-pets', 'communityFeed');
    });
});

// ==========================================
// FALLBACK ROUTE (Must be at the end)
// ==========================================
Route::fallback(function () {
    return response_error('The requested API endpoint does not exist.', [], 404);
});
