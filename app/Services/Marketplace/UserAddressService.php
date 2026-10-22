<?php

namespace App\Services\Marketplace;

use App\Models\User;
use App\Models\UserAddress;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class UserAddressService extends BaseService
{
    protected string $modelClass = UserAddress::class;
    protected bool $cachePerUser = true;

    protected function getAllowedFilters(): array
    {
        return [
            AllowedFilter::exact('is_default'),
            AllowedFilter::partial('city'),
            AllowedFilter::partial('state'),
            AllowedFilter::partial('street_address'),
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
     * Get all delivery addresses for a user with Spatie filtering, sorting & per-user caching
     */
    public function getUserAddresses(User $user): Collection
    {
        $callback = function () use ($user) {
            $baseQuery = $this->model->where('user_id', $user->id)
                ->orderByDesc('is_default')
                ->latest('id');

            return QueryBuilder::for($baseQuery)
                ->allowedFilters($this->getAllowedFilters())
                ->allowedIncludes($this->getAllowedIncludes())
                ->allowedSorts($this->getAllowedSorts())
                ->get();
        };

        return $this->cache(__FUNCTION__, func_get_args(), $callback, $this->cacheTTL, true);
    }

    /**
     * Save a new delivery address via BaseService::create
     */
    public function storeAddress(User $user, array $data): UserAddress
    {
        $data['user_id'] = $user->id;

        if (!empty($data['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        } else {
            // First address for user becomes default automatically
            if ($user->addresses()->count() === 0) {
                $data['is_default'] = true;
            }
        }

        return $this->create($data);
    }

    /**
     * Update existing delivery address via BaseService::update
     */
    public function updateAddress(User $user, UserAddress $address, array $data): UserAddress
    {
        $this->authorizeUser($user, $address);

        if (!empty($data['is_default'])) {
            $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        return $this->update($address->id, $data);
    }

    /**
     * Set address as default via BaseService::update
     */
    public function setDefaultAddress(User $user, UserAddress $address): UserAddress
    {
        $this->authorizeUser($user, $address);

        $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);

        return $this->update($address->id, ['is_default' => true]);
    }

    /**
     * Delete an address with fallback for default via BaseService::delete
     */
    public function deleteAddress(User $user, UserAddress $address): void
    {
        $this->authorizeUser($user, $address);

        $wasDefault = $address->is_default;
        $this->delete($address->id);

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
