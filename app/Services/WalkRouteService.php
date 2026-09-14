<?php

namespace App\Services;

use App\Models\WalkRoute;

class WalkRouteService extends BaseService
{
    protected string $modelClass = WalkRoute::class;

    protected function getAllowedFilters(): array
    {
        return ['name', 'is_favorite', 'user_id', \Spatie\QueryBuilder\AllowedFilter::custom('search', new \App\Filters\GlobalSearchFilter(), 'name')];
    }

    protected function getAllowedIncludes(): array
    {
        return ['user'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'name', 'distance_km', 'is_favorite'];
    }

    public function toggleFavorite(WalkRoute $route): WalkRoute
    {
        $route->update(['is_favorite' => !$route->is_favorite]);
        return $route;
    }
}

