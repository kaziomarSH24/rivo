<?php

namespace App\Policies;

use App\Models\WalkRoute;
use App\Models\User;

class WalkRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
    public function view(User $user, WalkRoute $walkRoute): bool
    {
        return $user->id === $walkRoute->user_id;
    }
    public function create(User $user): bool
    {
        return true;
    }
    public function update(User $user, WalkRoute $walkRoute): bool
    {
        return $user->id === $walkRoute->user_id;
    }
    public function delete(User $user, WalkRoute $walkRoute): bool
    {
        return $user->id === $walkRoute->user_id;
    }
}
