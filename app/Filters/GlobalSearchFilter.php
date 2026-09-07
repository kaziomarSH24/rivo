<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class GlobalSearchFilter implements Filter
{
    /**
     * This filter uses a single search term
     * to run 'LIKE' queries on multiple columns.
     *
     * @param Builder $query
     * @param mixed $value
     * @param string $property
     * @return void
     */
    public function __invoke(Builder $query, $value, string $property)
    {
        // First, split the columns
        $columns = explode(',', $property);

        $query->where(function (Builder $query) use ($columns, $value) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'LIKE', "%{$value}%");
            }
        });
    }
}
