<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorStoreRegistered
{
    /**
     * Handle an incoming request ensuring the authenticated user has a registered store.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response_error('Unauthenticated. Please login to continue.', [], 401);
        }

        $store = $user->store;
        if (!$store) {
            return response_error('No store profile associated with this account.', [], 404);
        }

        // Attach store to request attributes for easy retrieval
        $request->attributes->set('store', $store);

        return $next($request);
    }
}
