<?php

namespace App\Policies;

use App\Models\AppointmentNote;
use App\Models\User;

class AppointmentNotePolicy
{
    public function view(User $user, AppointmentNote $appointmentNote): bool
    {
        return $user->id === $appointmentNote->appointment->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AppointmentNote $appointmentNote): bool
    {
        return $user->id === $appointmentNote->appointment->user_id;
    }

    public function delete(User $user, AppointmentNote $appointmentNote): bool
    {
        return $user->id === $appointmentNote->appointment->user_id;
    }
}
