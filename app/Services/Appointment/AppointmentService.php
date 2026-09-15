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



