<?php

namespace App\Providers;

// use App\Models\Product;
// use App\Observers\ProductObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Relations\Relation;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Payment\PaymentGatewayManager::class, function ($app) {
            return new \App\Services\Payment\PaymentGatewayManager();
        });

        $this->app->bind(\App\Services\Payment\Contracts\PaymentGatewayInterface::class, function ($app) {
            return $app->make(\App\Services\Payment\PaymentGatewayManager::class)->gateway();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Defining super-admin through Gate
        // This code will run before checking any permission
        Gate::before(function ($user, $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        Relation::morphMap([
            'product' => Product::class,
            'store' => Store::class,
            Product::class => Product::class,
            Store::class => Store::class,
        ]);

        // Observers
        // Product::observe(ProductObserver::class);

    }

    protected $policies = [
        \Illuminate\Notifications\DatabaseNotification::class => \App\Policies\NotificationPolicy::class,
    ];
}
