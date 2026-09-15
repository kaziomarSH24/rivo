<?php

namespace App\Services\Appointment;



use App\Services\BaseService;

use App\Models\Appointment;

class AppointmentService extends BaseService
{
    protected string $modelClass = Appointment::class;

    protected function getAllowedFilters(): array
    {
        return [
            'status',
            'type',
            'vet_name',
            \Spatie\QueryBuilder\AllowedFilter::callback('date', function (\Illuminate\Database\Eloquent\Builder $query, $value) {
                $query->whereDate('datetime', \Carbon\Carbon::parse($value)->toDateString());
            }),
        ];
    }

    protected function getAllowedIncludes(): array
    {
        return [
            'pet',
            'notes',
        ];
    }

    protected function getAllowedSorts(): array
    {
        return [
            'datetime',
            'created_at',
            'status'
        ];
    }
}




