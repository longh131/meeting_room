<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            SuperAdminSeeder::class,
            MultiTenantSeeder::class,
            DeviceTagSeeder::class,
            ReservationSeeder::class,
        ]);
    }
}
