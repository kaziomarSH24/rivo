<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Closure;

/**
 * This Trait contains the core logic for creating and updating data in the database.
 * It will be used via BaseService.
 */
trait ManagesData
{
    /**
     * Creates or updates a record in the database.
     *
     * @param array $data - Array of validated data.
     * @param Model $modelInstance - The model instance to work with.
     * @param array $relations - Data for many-to-many relationships.
     * @param Closure|null $transactionalCallback  - Additional custom logic to run inside the transaction.
     * @return Model
     */
    protected function storeOrUpdate(array $data, Model $modelInstance, array $relations = [], ?Closure $transactionalCallback  = null): Model
    {
          $isCreating = !$modelInstance->exists;
        // The entire operation is wrapped in a database transaction
        return DB::transaction(function () use ($data, $modelInstance, $relations, $transactionalCallback, $isCreating ) {
            // 1. Fill the main model with data and save it
            $modelInstance->fill($data)->save();

            // 2. Manage relationships (if any)
            if (!empty($relations)) {
                $this->handleRelations($modelInstance, $relations, $isCreating);
            }

            // 3. Run additional custom logic (if any)
            if ($transactionalCallback ) {
                // Pass the newly created or updated model as an argument to the function
                $transactionalCallback ($modelInstance);
            }

            // Finally, return the model instance
            return $modelInstance;
        });
    }

    /**
     * Helper method to handle relational data.
     *
     * @param Model $model
     * @param array $relations
     */
    private function handleRelations(Model $model, array $relations, bool $isCreating = false)
    {
        foreach ($relations as $relationName => $relationData) {
            if (method_exists($model, $relationName)) {
                $relation = $model->$relationName();

                // use only for meny-to-many relationships
                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                    // if it's new record, use attach, otherwise sync
                    if ($isCreating) {
                        $relation->attach($relationData);
                    } else {
                        // if it's update, use sync
                        $relation->sync($relationData);
                    }
                }
            }
        }
    }
}

