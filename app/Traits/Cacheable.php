<?php

namespace App\Traits;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Trait Cacheable
 *
 * Provides granular, tag-based caching with automatic fallback for non-taggable drivers
 * (Redis, Memcached, File, Database). Supports standard CRUD model services, standalone
 * services (Dashboard/Analytics/Reports), custom tags, and cache stampede protection via atomic locks.
 *
 * @package App\Traits
 */
trait Cacheable
{
    /**
     * Caches the result of a closure using dynamically generated keys and tags.
     *
     * @param string $method Name of the calling method or unique identifier.
     * @param array $arguments Method arguments to incorporate into the cryptographic key.
     * @param Closure $callback Closure to execute when cache misses.
     * @param int|null $ttl Time-to-live in seconds (defaults to $this->cacheTTL or 3600).
     * @param bool $perUser Whether to scope the cache to the currently authenticated user.
     * @param array $customTags Optional explicit tags to override automatic tag resolution.
     * @param bool $lock Whether to use atomic locks to prevent cache stampede on heavy queries.
     * @return mixed
     */
    protected function cache(
        string $method,
        array $arguments,
        Closure $callback,
        ?int $ttl = null,
        bool $perUser = false,
        array $customTags = [],
        bool $lock = false
    ) {
        if (property_exists($this, 'cachingEnabled') && $this->cachingEnabled === false) {
            return $callback();
        }

        $ttl = $ttl ?? ($this->cacheTTL ?? 3600);
        $tags = !empty($customTags) ? $customTags : $this->determineCacheTags($method, $arguments);
        $key = $this->generateCacheKey($method, $arguments, $perUser);

        // Optional Atomic Lock for Cache Stampede protection (heavy analytics/dashboards)
        if ($lock) {
            return $this->executeWithLock($key, $ttl, $tags, $callback);
        }

        return $this->rememberInCache($key, $ttl, $tags, $callback);
    }

    /**
     * Directly cache a computation with a custom key and tags.
     * Convenient for ad-hoc caching in services, controllers, or jobs without passing method args.
     *
     * @param string $key Custom cache key.
     * @param Closure $callback Closure to execute when cache misses.
     * @param int|null $ttl Time-to-live in seconds (defaults to $this->cacheTTL or 3600).
     * @param array $tags Cache tags to assign (e.g. ['dashboard', 'reports']).
     * @param bool $lock Whether to acquire an atomic lock during computation.
     * @return mixed
     */
    protected function rememberCache(
        string $key,
        Closure $callback,
        ?int $ttl = null,
        array $tags = [],
        bool $lock = false
    ) {
        if (property_exists($this, 'cachingEnabled') && $this->cachingEnabled === false) {
            return $callback();
        }

        $ttl = $ttl ?? ($this->cacheTTL ?? 3600);
        $tags = !empty($tags) ? $tags : [class_basename($this)];

        if ($lock) {
            return $this->executeWithLock($key, $ttl, $tags, $callback);
        }

        return $this->rememberInCache($key, $ttl, $tags, $callback);
    }

    /**
     * Executes cache remember logic with driver safety for tags.
     */
    private function rememberInCache(string $key, int $ttl, array $tags, Closure $callback)
    {
        try {
            if (Cache::supportsTags() && !empty($tags)) {
                return Cache::tags($tags)->remember($key, $ttl, $callback);
            }
        } catch (\BadMethodCallException|\Throwable $e) {
            // Driver does not support tagging (e.g. file, database)
        }

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Executes the closure with an atomic lock to prevent cache stampede.
     */
    private function executeWithLock(string $key, int $ttl, array $tags, Closure $callback)
    {
        // 1. Check if item already exists in cache before attempting lock
        try {
            if (Cache::supportsTags() && !empty($tags)) {
                if (Cache::tags($tags)->has($key)) {
                    return Cache::tags($tags)->get($key);
                }
            } else {
                if (Cache::has($key)) {
                    return Cache::get($key);
                }
            }
        } catch (\Throwable $e) {
            // Ignore pre-check error
        }

        $lockName = 'lock:' . $key;
        try {
            // Acquire atomic lock for 15 seconds while computing
            return Cache::lock($lockName, 15)->block(5, function () use ($key, $ttl, $tags, $callback) {
                // Inside lock, recheck if another process just filled the cache
                try {
                    if (Cache::supportsTags() && !empty($tags)) {
                        if (Cache::tags($tags)->has($key)) {
                            return Cache::tags($tags)->get($key);
                        }
                    } else {
                        if (Cache::has($key)) {
                            return Cache::get($key);
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore recheck error
                }

                $result = $callback();

                try {
                    if (Cache::supportsTags() && !empty($tags)) {
                        Cache::tags($tags)->put($key, $result, $ttl);
                    } else {
                        Cache::put($key, $result, $ttl);
                    }
                } catch (\Throwable $e) {
                    Cache::put($key, $result, $ttl);
                }

                return $result;
            });
        } catch (\Throwable $e) {
            // Fallback to standard execution if locking is unsupported or timed out
            return $this->rememberInCache($key, $ttl, $tags, $callback);
        }
    }

    /**
     * Determine accurate cache tags based on the operation.
     * Single-item operations use strictly isolated item tags (e.g., 'users:8')
     * so that updating one item does NOT flush caches for all other items.
     * Collection/list operations use the table collection tag (e.g., 'users').
     */
    private function determineCacheTags(string $method, array $arguments): array
    {
        if (property_exists($this, 'model') && $this->model instanceof Model) {
            $tableName = $this->model->getTable();

            // 1. Single Item Operations (e.g., getById, update, delete) -> isolate strictly to item tag
            if (in_array($method, ['getById', 'update', 'delete']) && isset($arguments[0])) {
                $id = $arguments[0];
                if (is_scalar($id)) {
                    return [$tableName . ':' . $id]; // e.g., ['users:8']
                }
            }

            // 2. Querying by primary key via findByOrFail (e.g., findByOrFail('id', 8))
            if ($method === 'findByOrFail' && isset($arguments[0], $arguments[1])) {
                if ($arguments[0] === $this->model->getKeyName() && is_scalar($arguments[1])) {
                    return [$tableName . ':' . $arguments[1]];
                }
            }

            // 3. Collection queries (getAll, paginate, etc.) -> use table collection tag
            return [$tableName]; // e.g., ['users']
        }

        // Fallback for classes without an Eloquent model (e.g., DashboardService)
        return [class_basename($this)];
    }

    /**
     * Clears the cached entry (e.g., getById) for a specific model instance.
     *
     * @param Model $modelInstance The model instance whose cache needs to be cleared.
     */
    public function clearItemCache(Model $modelInstance): void
    {
        $itemTag = $modelInstance->getTable() . ':' . $modelInstance->getKey();
        $key = $this->generateCacheKey('getById', [$modelInstance->getKey()], $this->cachePerUser ?? false);

        try {
            if (Cache::supportsTags()) {
                Cache::tags($itemTag)->flush();
            } else {
                Cache::forget($key);
            }
        } catch (\BadMethodCallException|\Throwable $e) {
            Cache::forget($key);
        }
    }

    /**
     * Flushes all cached collection queries for this service or model's table.
     *
     * @param string|null $tableName Explicit table or tag name to flush.
     */
    public function clearTableCache(?string $tableName = null): void
    {
        $tag = $tableName;

        if (!$tag && property_exists($this, 'model') && $this->model instanceof Model) {
            $tag = $this->model->getTable();
        }

        if (!$tag) {
            $tag = class_basename($this);
        }

        try {
            if (Cache::supportsTags()) {
                Cache::tags($tag)->flush();
            }
        } catch (\BadMethodCallException|\Throwable $e) {
            // Driver does not support tagging
        }
    }

    /**
     * Invalidate one or more custom cache tags.
     *
     * @param string|array $tags Tag name or array of tag names to flush.
     */
    public function flushCacheTags(string|array $tags): void
    {
        $tags = (array) $tags;
        try {
            if (Cache::supportsTags()) {
                foreach ($tags as $tag) {
                    Cache::tags($tag)->flush();
                }
            }
        } catch (\BadMethodCallException|\Throwable $e) {
            // Driver does not support tagging
        }
    }

    /**
     * Generates a robust cryptographic cache key.
     */
    private function generateCacheKey(string $method, array $arguments, bool $perUser): string
    {
        $keyParts = [
            class_basename($this),
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

            if ($value instanceof Model) {
                return get_class($value) . ':' . ($value->getKey() ?? 'null');
            }

            if (is_object($value)) {
                return get_class($value);
            }

            return $value;
        };

        $safeArguments = array_map($normalize, $arguments);
        $argumentString = serialize($safeArguments);

        // Handle Request Queries for exact match in lists/dashboards
        $queryString = '';
        if (in_array($method, ['getAll', 'paginate', 'index']) || request()->has('page') || request()->has('filter')) {
            $queryParams = request()->query();
            ksort($queryParams);
            $queryString = serialize($queryParams);
        }

        // SHA-256 hash creates a fast, unique, and safe key
        $keyParts[] = hash('sha256', $argumentString . $queryString);

        return implode(':', $keyParts);
    }
}