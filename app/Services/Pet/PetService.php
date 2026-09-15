<?php

namespace App\Services\Pet;



use App\Services\BaseService;

use App\Models\Pet;
use App\Models\User;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;

class PetService extends BaseService
{
    use FileUploadTrait;

    protected string $modelClass = Pet::class;

    protected function getAllowedFilters(): array
    {
        return ['type', 'breed', 'gender', 'user_id'];
    }

    protected function getAllowedIncludes(): array
    {
        return ['user'];
    }

    protected function getAllowedSorts(): array
    {
        return ['created_at', 'name', 'age_years', 'weight'];
    }

    /**
     * Create a new pet for a user.
     */
    public function createPet(array $data, User $user, Request $request): Pet
    {
        $data['user_id'] = $user->id;

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->handleFileUpload($request, 'photo', 'pets', 800, 800, 80, true);
        }

        return $this->create($data);
    }

    /**
     * Update a pet.
     */
    public function updatePet(Pet $pet, array $data, Request $request): Pet
    {
        if ($request->hasFile('photo')) {
            if ($pet->photo) {
                $this->deleteFile($pet->photo);
            }
            $data['photo'] = $this->handleFileUpload($request, 'photo', 'pets', 800, 800, 80, true);
        }

        return $this->update($pet->id, $data);
    }

    /**
     * Delete a pet and its photo.
     */
    public function deletePet(Pet $pet): bool
    {
        if ($pet->photo) {
            $this->deleteFile($pet->photo);
        }
        return $this->delete($pet->id);
    }
}



