<?php

namespace App\Services;

use App\Filters\GlobalSearchFilter;
use App\Services\BaseService;
use App\Models\User;
use Spatie\QueryBuilder\AllowedFilter;

class UserService extends BaseService
{
    /**
     * The model class name.
     *
     * @var string
     */
    protected string $modelClass = User::class;

    protected bool $cachingEnabled = true;

    public function __construct()
    {
        // Ensure BaseService initializes the model instance
        parent::__construct();

    }

    /**
     * Which fields are allowed to be filtered by.
     * @var array
     */
    protected function getAllowedFilters(): array
    {
        return [
            AllowedFilter::custom('search', new GlobalSearchFilter, 'first_name,last_name,email'),
            'first_name',
            'email',
            AllowedFilter::exact('status'),
        ];
    }

    /**
     * Which fields are allowed to be sorted by.
     * @var array
     */
    protected function getAllowedSorts(): array
    {
        return [
            'id',
            'first_name',
            'created_at',
        ];
    }

    /**
     * Which relationships are allowed to be loaded.
     * @var array
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'roles',
        ];
    }


}
