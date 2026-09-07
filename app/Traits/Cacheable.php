<?php

namespace App\Traits;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

trait Cacheable
{
    /**
     * Caches the result of a closure using a dynamically generated key and tags.
     */
    protected function cache(string $method, array $arguments, Closure $callback, int $ttl = 3600, bool $perUser = false)
    {
        if (property_exists($this, 'cachingEnabled') && $this->cachingEnabled === false) {
            return $callback();
        }

        if (!property_exists($this, 'model')) {
            return $callback();
        }

        // Determine tags based on the method
        $tags = $this->determineCacheTags($method, $arguments);
        $key = $this->generateCacheKey($method, $arguments, $perUser);

        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    /**
     * Determine accurate cache tags based on the operation.
     */
    private function determineCacheTags(string $method, array $arguments): array
    {
        $tableName = $this->model->getTable();
        $tags = [$tableName]; // Base collection tag is always applied

        // If dealing with a specific item (e.g., getById, update), attach the item-specific tag
        if (in_array($method, ['getById', 'update', 'delete']) && isset($arguments[0])) {
            $id = $arguments[0];
            if (is_scalar($id)) {
                $tags[] = $tableName . ':' . $id; // e.g., 'users:5'
            }
        }

        return $tags;
    }

     /**
     * Clears the cache for a single item (e.g., getById).
     * This method is intended to be called from an observer.
     *
     * @param Model $modelInstance The model instance whose cache needs to be cleared.
     */
    // public function clearItemCache(Model $modelInstance): void
    // {
    //     if (!property_exists($this, 'model')) {
    //         return;
    //     }

    //     $tag = $modelInstance->getTable();
    //     $key = $this->generateCacheKey('getById', [$modelInstance->getKey()], $this->cachePerUser ?? false);
    //     Cache::tags($tag)->forget($key);
    // }

    /**
     * Generates a robust cryptographic cache key.
     */
    private function generateCacheKey(string $method, array $arguments, bool $perUser): string
    {
        $keyParts = [
            class_basename($this), // Shorter class name is better for cache key length
            $method,
        ];

        if ($perUser) {
            $user = auth('sanctum')->user() ?? request()->user() ?? auth()->user();
            $keyParts[] = 'user_' . ($user?->getAuthIdentifier() ?? 'guest');
        }

        // Normalize arguments to avoid serializing Closures or complex objects
        $normalize = function ($value) use (&$normalize) {
            if ($value instanceof Closure) {
                return 'closure';
            }

            if (is_array($value)) {
                return array_map($normalize, $value);
            }

            if (is_object($value)) {
                // For Eloquent models, include class and primary key when available
                if (method_exists($value, 'getKey')) {
                    return get_class($value) . ':' . ($value->getKey() ?? 'null');
                }

                // Fallback to class name for other objects
                return get_class($value);
            }

            return $value;
        };

        $safeArguments = array_map($normalize, $arguments);
        $argumentString = serialize($safeArguments);

        // Handle Request Queries for exact match in lists
        $queryString = '';
        if ($method === 'getAll' || $method === 'paginate') {
            $queryParams = request()->query();
            ksort($queryParams);
            $queryString = serialize($queryParams);
        }

        // SHA-256 hash creates a fast, unique, and perfectly safe key for complex parameters
        $keyParts[] = hash('sha256', $argumentString . $queryString);

        return implode(':', $keyParts);
    }
}
