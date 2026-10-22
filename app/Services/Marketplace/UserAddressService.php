<?php

namespace App\Services\Marketplace;

use App\Models\User;
use App\Models\UserAddress;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserAddressService extends BaseService
{
    protected string $modelClass = UserAddress::class;

    protected function getAllowedFilters(): array
    {
        return [
            AllowedFilter::exact('is_default'),
            AllowedFilter::partial('city'),
            AllowedFilter::partial('state'),
        ];
    }

    protected function getAllowedIncludes(): array
    {
        return ['user'];
    }

    protected function getAllowedSorts(): array
    {
        return ['is_default', 'created_at', 'id'];
    }

    /**
     * Get all delivery addresses for a user
     */
    public function getUserAddresses(User $user): Collection
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->latest('id')
            ->get();
    }

    /**
     * Save a new delivery address
     */
    public function storeAddress(User $user, array $data): UserAddress
    {
        if (!empty($data['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        } else {
            // First address for user becomes default automatically
            if ($user->addresses()->count() === 0) {
                $data['is_default'] = true;
            }
        }

        return $user->addresses()->create($data);
    }

    /**
     * Update existing delivery address
     */
    public function updateAddress(User $user, UserAddress $address, array $data): UserAddress
    {
        $this->authorizeUser($user, $address);

        if (!empty($data['is_default'])) {
            $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($data);

        return $address->refresh();
    }

    /**
     * Set address as default
     */
    public function setDefaultAddress(User $user, UserAddress $address): UserAddress
    {
        $this->authorizeUser($user, $address);

        $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return $address->refresh();
    }

    /**
     * Delete an address with fallback for default
     */
    public function deleteAddress(User $user, UserAddress $address): void
    {
        $this->authorizeUser($user, $address);

        $wasDefault = $address->is_default;
        $address->delete();

        // If the deleted address was default, promote the latest remaining address to default
        if ($wasDefault) {
            $user->addresses()->latest('id')->first()?->update(['is_default' => true]);
        }
    }

    /**
     * Authorize that the address belongs to the user
     */
    protected function authorizeUser(User $user, UserAddress $address): void
    {
        if ($address->user_id !== $user->id) {
            throw new AccessDeniedHttpException('Unauthorized access to this address.');
        }
    }
}
