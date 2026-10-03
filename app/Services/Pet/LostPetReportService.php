<?php

namespace App\Services\Pet;

use App\Models\LostPetReport;
use App\Models\Pet;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;

class LostPetReportService extends BaseService
{
    protected string $modelClass = LostPetReport::class;
    
    // Disable caching for this service as reports need to be very real-time
    protected bool $cachingEnabled = false;

    protected function getAllowedFilters(): array
    {
        return [
            'status',
            'pet_id',
            'user_id',
            'last_seen_location',
        ];
    }

    protected function getAllowedIncludes(): array
    {
        return [
            'pet',
            'user'
        ];
    }

    protected function getAllowedSorts(): array
    {
        return [
            'created_at',
            'last_seen_date',
        ];
    }

    public function createReport(Pet $pet, array $data, $imageFile = null)
    {
        return DB::transaction(function () use ($pet, $data, $imageFile) {
            // Check if already has an active report
            $existing = $pet->lostReports()->where('status', 'active')->first();
            if ($existing) {
                throw new \Exception("An active lost report already exists for this pet.");
            }

            if ($imageFile) {
                $path = $imageFile->store('lost_pets', 'public');
                $data['last_seen_photo'] = Storage::url($path);
            }

            $data['pet_id'] = $pet->id;
            $data['status'] = 'active';

            $report = $this->create($data);

            // Update pet status
            $pet->update(['is_lost' => true]);

            return $report;
        });
    }

    public function markAsFound(Pet $pet)
    {
        return DB::transaction(function () use ($pet) {
            $report = $pet->lostReports()->where('status', 'active')->first();
            
            if (!$report) {
                throw new \Exception("No active lost report found for this pet.");
            }

            $report->update(['status' => 'found']);
            $pet->update(['is_lost' => false]);

            return $report;
        });
    }
}
