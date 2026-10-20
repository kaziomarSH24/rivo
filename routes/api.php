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

    // --- Pet Badges (Achievements) ---
    Route::controller(\App\Http\Controllers\Api\V1\Pet\PetBadgeController::class)->prefix('pets/{pet}/badges')->group(function () {
        Route::get('/', 'index');
        Route::post('display', 'display');
    });

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

    // Social Module (Phase 1: Match & Connections)
    Route::prefix('social')->group(function () {
        // Discovery & Match
        Route::get('discover/{pet}', [\App\Http\Controllers\Api\V1\Social\MatchController::class, 'discover']);
        Route::post('swipe/{pet}', [\App\Http\Controllers\Api\V1\Social\MatchController::class, 'swipe']);

        // Friends & Requests
        Route::get('match-list/{pet}', [\App\Http\Controllers\Api\V1\Social\ConnectionController::class, 'index']);
        Route::get('requests/{pet}', [\App\Http\Controllers\Api\V1\Social\ConnectionController::class, 'requests']);
        Route::post('request/send/{pet}', [\App\Http\Controllers\Api\V1\Social\ConnectionController::class, 'sendRequest']);
        Route::post('request/respond/{pet}', [\App\Http\Controllers\Api\V1\Social\ConnectionController::class, 'respond']);

                // Saved Posts / Bookmarks
        Route::get('posts/saved', [\App\Http\Controllers\Api\V1\Social\PostBookmarkController::class, 'index']);
        Route::post('posts/{post}/bookmark', [\App\Http\Controllers\Api\V1\Social\PostBookmarkController::class, 'toggle']);

        // Social Feed & Posts
        Route::apiResource('posts', \App\Http\Controllers\Api\V1\Social\PostController::class);
        Route::post('posts/{post}/like', [\App\Http\Controllers\Api\V1\Social\PostController::class, 'toggleLike']);
        Route::post('posts/{post}/react', [\App\Http\Controllers\Api\V1\Social\PostController::class, 'toggleLike']);
        Route::get('posts/{post}/comments', [\App\Http\Controllers\Api\V1\Social\PostCommentController::class, 'index']);
        Route::post('posts/{post}/comments', [\App\Http\Controllers\Api\V1\Social\PostCommentController::class, 'store']);
        Route::delete('posts/{post}/comments/{comment}', [\App\Http\Controllers\Api\V1\Social\PostCommentController::class, 'destroy']);
    });

    // ==========================================
    // VENDOR / MERCHANT PORTAL MODULE
    // ==========================================
    Route::prefix('vendor')->group(function () {
        // Store Onboarding & Profile
        Route::post('store', [\App\Http\Controllers\Api\V1\Vendor\VendorStoreController::class, 'store']);
        Route::get('profile', [\App\Http\Controllers\Api\V1\Vendor\VendorStoreController::class, 'profile']);
        Route::post('profile', [\App\Http\Controllers\Api\V1\Vendor\VendorStoreController::class, 'updateProfile']);
        Route::post('documents', [\App\Http\Controllers\Api\V1\Vendor\VendorStoreController::class, 'uploadDocument']);
        Route::delete('documents/{document}', [\App\Http\Controllers\Api\V1\Vendor\VendorStoreController::class, 'deleteDocument']);
        Route::get('dashboard', [\App\Http\Controllers\Api\V1\Vendor\VendorStoreController::class, 'dashboard']);
        Route::get('analytics', [\App\Http\Controllers\Api\V1\Vendor\VendorStoreController::class, 'analytics']);

        // Catalog & 4-Step Product Wizard
        Route::get('products', [\App\Http\Controllers\Api\V1\Vendor\VendorProductController::class, 'index']);
        Route::post('products', [\App\Http\Controllers\Api\V1\Vendor\VendorProductController::class, 'store']);
        Route::get('products/{product}', [\App\Http\Controllers\Api\V1\Vendor\VendorProductController::class, 'show']);
        Route::post('products/{product}', [\App\Http\Controllers\Api\V1\Vendor\VendorProductController::class, 'update']);
        Route::patch('products/{product}/stock', [\App\Http\Controllers\Api\V1\Vendor\VendorProductController::class, 'updateStock']);
        Route::patch('products/{product}/toggle-active', [\App\Http\Controllers\Api\V1\Vendor\VendorProductController::class, 'toggleActive']);
        Route::delete('products/{product}', [\App\Http\Controllers\Api\V1\Vendor\VendorProductController::class, 'destroy']);

        // Order Management & Fulfillment Stepper
        Route::get('orders', [\App\Http\Controllers\Api\V1\Vendor\VendorOrderController::class, 'index']);
        Route::get('orders/{order}', [\App\Http\Controllers\Api\V1\Vendor\VendorOrderController::class, 'show']);
        Route::post('orders/{order}/accept', [\App\Http\Controllers\Api\V1\Vendor\VendorOrderController::class, 'accept']);
        Route::post('orders/{order}/reject', [\App\Http\Controllers\Api\V1\Vendor\VendorOrderController::class, 'reject']);
        Route::patch('orders/{order}/status', [\App\Http\Controllers\Api\V1\Vendor\VendorOrderController::class, 'updateStatus']);
    });

    // ==========================================
    // MARKETPLACE (CUSTOMER) MODULE
    // ==========================================
    Route::prefix('marketplace')->group(function () {
        // Discovery & Catalog
        Route::get('home', [\App\Http\Controllers\Api\V1\Marketplace\MarketplaceHomeController::class, 'index']);
        Route::get('stores', [\App\Http\Controllers\Api\V1\Marketplace\MarketplaceHomeController::class, 'stores']);
        Route::get('categories', [\App\Http\Controllers\Api\V1\Marketplace\MarketplaceHomeController::class, 'categories']);
        Route::get('categories/{category}/products', [\App\Http\Controllers\Api\V1\Marketplace\MarketplaceHomeController::class, 'categoryProducts']);
        Route::get('products', [\App\Http\Controllers\Api\V1\Marketplace\MarketplaceHomeController::class, 'products']);
        Route::get('search', [\App\Http\Controllers\Api\V1\Marketplace\MarketplaceHomeController::class, 'search']);
        Route::delete('search/recent', [\App\Http\Controllers\Api\V1\Marketplace\MarketplaceHomeController::class, 'clearRecentSearches']);
        Route::get('stores/{store}', [\App\Http\Controllers\Api\V1\Marketplace\MarketplaceHomeController::class, 'storeProfile']);
        Route::get('products/{product}', [\App\Http\Controllers\Api\V1\Marketplace\MarketplaceHomeController::class, 'productDetails']);

        // Reviews
        Route::post('reviews', [\App\Http\Controllers\Api\V1\Marketplace\ReviewController::class, 'store']);
        Route::get('products/{product}/reviews', [\App\Http\Controllers\Api\V1\Marketplace\ReviewController::class, 'productReviews']);
        Route::get('stores/{store}/reviews', [\App\Http\Controllers\Api\V1\Marketplace\ReviewController::class, 'storeReviews']);

        // Delivery Addresses
        Route::get('addresses', [\App\Http\Controllers\Api\V1\Marketplace\UserAddressController::class, 'index']);
        Route::post('addresses', [\App\Http\Controllers\Api\V1\Marketplace\UserAddressController::class, 'store']);
        Route::put('addresses/{address}', [\App\Http\Controllers\Api\V1\Marketplace\UserAddressController::class, 'update']);
        Route::patch('addresses/{address}/default', [\App\Http\Controllers\Api\V1\Marketplace\UserAddressController::class, 'setDefault']);
        Route::delete('addresses/{address}', [\App\Http\Controllers\Api\V1\Marketplace\UserAddressController::class, 'destroy']);

        // Cart
        Route::get('cart', [\App\Http\Controllers\Api\V1\Marketplace\CartController::class, 'index']);
        Route::post('cart/items', [\App\Http\Controllers\Api\V1\Marketplace\CartController::class, 'addItem']);
        Route::patch('cart/items/{item}', [\App\Http\Controllers\Api\V1\Marketplace\CartController::class, 'updateItem']);
        Route::delete('cart/items/{item}', [\App\Http\Controllers\Api\V1\Marketplace\CartController::class, 'removeItem']);
        Route::delete('cart', [\App\Http\Controllers\Api\V1\Marketplace\CartController::class, 'clear']);

        // Checkout & Orders
        Route::post('checkout', [\App\Http\Controllers\Api\V1\Marketplace\CheckoutController::class, 'process']);
        Route::get('orders', [\App\Http\Controllers\Api\V1\Marketplace\CustomerOrderController::class, 'index']);
        Route::get('orders/{order}', [\App\Http\Controllers\Api\V1\Marketplace\CustomerOrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [\App\Http\Controllers\Api\V1\Marketplace\CustomerOrderController::class, 'cancel']);
    });
});

// ==========================================
// FALLBACK ROUTE (Must be at the end)
// ==========================================
Route::fallback(function () {
    return response_error('The requested API endpoint does not exist.', [], 404);
});




