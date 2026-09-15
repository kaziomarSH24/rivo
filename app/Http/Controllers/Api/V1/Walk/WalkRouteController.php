<?php

namespace App\Http\Controllers\Api\V1\Walk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Walk\StoreWalkRouteRequest;
use App\Http\Resources\Walk\WalkRouteResource;
use App\Models\WalkRoute;
use App\Services\Walk\WalkRouteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WalkRouteController extends Controller
{
    protected WalkRouteService $routeService;

    public function __construct(WalkRouteService $routeService)
    {
        $this->routeService = $routeService;
    }

    /**
     * List user's saved routes
     */
    public function index(Request $request)
    {
        $routes = $this->routeService->getAll(function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        });

        return response_success('Routes retrieved', WalkRouteResource::collection($routes)->response()->getData(true));
    }

    /**
     * Save a new route
     */
    public function store(StoreWalkRouteRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $route = $this->routeService->create($data);

        return response_success('Route saved', new WalkRouteResource($route), 201);
    }

    /**
     * Delete a route
     */
    public function destroy(WalkRoute $route, Request $request)
    {
        Gate::authorize('update', $route);

        $this->routeService->delete($route->id);
        return response_success('Route deleted');
    }

    /**
     * Toggle Favorite
     */
    public function toggleFavorite(WalkRoute $route, Request $request)
    {
        Gate::authorize('update', $route);

        $updated = $this->routeService->toggleFavorite($route);
        return response_success('Route updated', new WalkRouteResource($updated));
    }
}






