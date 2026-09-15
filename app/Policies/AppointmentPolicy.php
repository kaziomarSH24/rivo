<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->id === $appointment->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->id === $appointment->user_id;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->id === $appointment->user_id;
    }
}
