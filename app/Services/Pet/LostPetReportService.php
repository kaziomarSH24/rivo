<?php

namespace App\Services\Pet;

use App\Models\LostPetReport;
use App\Models\Pet;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;

class LostPetReportService extends BaseService
{
    use FileUploadTrait;
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

    public function createReport(Pet $pet, array $data, Request $request)
    {
        return DB::transaction(function () use ($pet, $data, $request) {
            // Check if already has an active report
            $existing = $pet->lostReports()->where('status', 'active')->first();
            if ($existing) {
                throw new \Exception("An active lost report already exists for this pet.");
            }
            
            $filePath = $this->handleFileUpload($request, 'last_seen_photo', 'lost_pets', 800, null, 80, true);
            
            if ($filePath) {
                $data['last_seen_photo'] = $filePath;
            }

            $data['pet_id'] = $pet->id;
            $data['status'] = 'active';

            $report = $this->create($data);

            // Update pet status
            $pet->update(['is_lost' => true]);

            return $report;
        });
    }

    /**
     * Publish the active lost report to the community social feed
     */
    public function postToCommunity(Pet $pet, array $data, $user)
    {
        $report = $pet->lostReports()->where('status', 'active')->first();

        if (!$report) {
            throw new \Exception("No active lost report found to post.");
        }

        // Create a social post related to this report
        return $report->posts()->create([
            'user_id' => $user->id,
            'pet_id' => $pet->id,
            'content' => $data['content'] ?? null,
            'type' => 'lost_pet',
        ]);
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





