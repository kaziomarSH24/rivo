<?php

namespace App\Policies;

use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class NotificationPolicy
{
     use HandlesAuthorization;
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DatabaseNotification $databaseNotification): bool
    {
        return $user->id === $notification->notifiable_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->id === $notification->notifiable_id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DatabaseNotification $databaseNotification): bool
    {
        return $user->id === $notification->notifiable_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DatabaseNotification $databaseNotification): bool
    {
       return $user->id === $notification->notifiable_id;
    }

}
