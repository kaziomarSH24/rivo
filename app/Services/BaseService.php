<?php

namespace App\Services;

use App\Traits\Cacheable;
use App\Traits\ManagesData;
use Illuminate\Database\Eloquent\Model;
use Closure;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Class BaseService
 *
 * An abstract base service layer for handling common CRUD operations.
 * Services extending this class must set the `$modelClass` property to the fully-qualified
 * class name of the Eloquent model they will manage.
 *
 * This class uses the `ManagesData` trait to centralize create/update logic.
 *
 * @package App\Services
 */
abstract class BaseService
{
    use ManagesData, Cacheable;

    /**
     * The fully qualified class name of the model.
     *
     * Example:
     *   protected string $modelClass = \App\Models\User::class;
     *
     * @var string
     */
    protected string $modelClass;

    /**
     * The model instance for performing queries.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected Model $model;


     /**
     * This property controls whether caching is active for this service.
     * By default, caching is enabled for all services that extend BaseService.
     * To disable caching for a specific service, override this property
     * and set it to `false` in the child service class.
     *
     * @var bool
     */
    protected bool $cachingEnabled = true;

    /**
     * Determines whether the cache for this service should be user-specific or global.
     * By default, all cache is global. Child services can override this property and set it to `true`
     * if user-specific caching is required.
     *
     * @var bool
     */
    protected bool $cachePerUser = false;

    /**
     * Default cache time in seconds (Time-To-Live). Default is 1 hour.
     * Child services can override this value for specific needs.
     *
     * @var int
     */
    protected int $cacheTTL = 3600; // Default: 1 hour

    // --- Query Builder Properties ---
    // --- Abstract Methods to be implemented by child services ---
    abstract protected function getAllowedFilters(): array;
    abstract protected function getAllowedIncludes(): array;
    abstract protected function getAllowedSorts(): array;

    /**
     * BaseService constructor.
     *
     * Resolves the model class from the Laravel service container.
     */
    public function __construct()
    {
        $this->model = app($this->modelClass);
    }

    /**
     * Retrieve all records with optional relationships, pagination, dynamic ordering, and custom queries.
     *
     * @param Closure|null  $queryCallback   A closure to apply custom query
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
 public function getAll(?Closure $queryCallback = null)
    {
        $callback = function () use ($queryCallback) {
            // First, a base Eloquent query builder is created.
            $baseQuery = $this->model->query();

            // If a custom query callback is provided, apply it.
            if ($queryCallback) {
                $queryCallback($baseQuery);
            }
            // Then, pass this (potentially modified) builder to Spatie QueryBuilder.
            return QueryBuilder::for($baseQuery)
                ->allowedFilters($this->getAllowedFilters())
                ->allowedIncludes($this->getAllowedIncludes())
                ->allowedSorts($this->getAllowedSorts())
                ->paginate(request()->input('per_page', 15))
                ->appends(request()->query());
        };
        // func_get_args() returns all arguments as an array
        return $this->cache(__FUNCTION__, func_get_args(), $callback, $this->cacheTTL, $this->cachePerUser);
    }

    /**
     * Retrieve a single record by its primary key.
     *
     * @param int   $id    The primary key value.
     * @param array $with  Relationships to eager load.
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getById(int|string $id, array $with = [])
    {
        $callback = fn() => $this->model->with($with)->findOrFail($id);
        return $this->cache(__FUNCTION__, func_get_args(), $callback, $this->cacheTTL, $this->cachePerUser);
    }

    /**
     * Retrieve a single record by a specific column and value, or throw an exception if not found.
     *
     * @param String $column
     * @param $value
     * @param array $with
     * @return \Illuminate\Database\Eloquent\Model
     */
     public function findByOrFail(string $column, $value, array $with = [])
    {
       $callback = fn() => $this->model->with($with)->where($column, $value)->firstOrFail();
        return $this->cache(__FUNCTION__, func_get_args(), $callback, $this->cacheTTL, $this->cachePerUser);
    }

    /**
     * Create a new record in the database.
     *
     * @param array        $data                  Data to be saved.
     * @param array        $relations             Related models to sync or attach.
     * @param Closure|null $transactionalCallback Optional transactional logic after save.
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $data, array $relations = [], ?Closure $transactionalCallback = null)
    {
        return $this->storeOrUpdate($data, new $this->modelClass, $relations, $transactionalCallback);
    }

    /**
     * Update an existing record in the database.
     *
     * @param int          $id                    Primary key of the record to update.
     * @param array        $data                  Data to be updated.
     * @param array        $relations             Related models to sync or attach.
     * @param Closure|null $transactionalCallback Optional transactional logic after update.
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function update(int|string $id, array $data, array $relations = [], ?Closure $transactionalCallback = null)
    {
        $record = $this->getById($id);
        $this->storeOrUpdate($data, $record, $relations, $transactionalCallback);
        return $record->refresh();
    }

    /**
     * Delete a record by its primary key.
     *
     * @param int $id Primary key of the record to delete.
     * @return bool
     */
    public function delete(int|string $id): bool
    {
        return $this->getById($id)->delete();
    }
}
