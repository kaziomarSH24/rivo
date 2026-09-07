<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait AutoClearsCache
{
    /**
     * Boot the trait and register Eloquent events.
     * Laravel automatically calls boot[TraitName] methods.
     */
    public static function bootAutoClearsCache(): void
    {
        // Fired after a model is created or updated
        static::saved(function (Model $model) {
            static::invalidateCache($model);
        });

        // Fired after a model is deleted
        static::deleted(function (Model $model) {
            static::invalidateCache($model);
        });
    }

    /**
     * Advanced cache invalidation logic.
     */
    protected static function invalidateCache(Model $model): void
    {
        // DB::afterCommit ensures cache is flushed ONLY if the transaction was completely successful.
        // This completely eliminates race conditions in high-traffic APIs.
        DB::afterCommit(function () use ($model) {
            $tableName = $model->getTable();

            // Tag 1: The specific item tag (e.g., 'users:5')
            $itemTag = $tableName . ':' . $model->getKey();

            // Tag 2: The collection tag (e.g., 'users')
            $collectionTag = $tableName;

            // We flush specific item and the collection.
            Cache::tags([$itemTag, $collectionTag])->flush();

            // If the model defines related cache tags to clear (e.g. ['posts', 'comments'])
            if (method_exists($model, 'getRelatedCacheTags')) {
                $relatedTags = $model->getRelatedCacheTags();
                if (!empty($relatedTags)) {
                    Cache::tags($relatedTags)->flush();
                }
            }
        });
    }
}
