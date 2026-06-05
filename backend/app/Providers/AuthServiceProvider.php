<?php

namespace App\Providers;

use App\Models\Reservation;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        //
    ];

    public function boot()
    {
        $this->registerPolicies();

        Gate::define('admin', function ($user) {
            return $user->is_admin;
        });

        Gate::define('update', function ($user, $reservation) {
            return $user->id === $reservation->user_id || $user->is_admin;
        });
    }
}