<?php

namespace App\Providers;

// use App\Models\Product;
// use App\Observers\ProductObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        // Observers
        // Product::observe(ProductObserver::class);

    }

    protected $policies = [
        \Illuminate\Notifications\DatabaseNotification::class => \App\Policies\NotificationPolicy::class,
    ];
}
