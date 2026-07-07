<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function view(User $user, Reservation $reservation): bool
    {
        return $user->is_super_admin
            || $user->tenant_id === $reservation->tenant_id;
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $user->is_super_admin
            || ($user->tenant_id === $reservation->tenant_id
                && ($user->id === $reservation->user_id
                    || $user->id === $reservation->booked_by_user_id
                    || $user->is_admin));
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $this->update($user, $reservation);
    }

    public function approve(User $user): bool
    {
        return $user->is_admin || $user->is_manager;
    }
}
