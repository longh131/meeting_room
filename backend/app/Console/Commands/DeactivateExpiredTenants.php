<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeactivateExpiredTenants extends Command
{
    protected $signature = 'tenants:deactivate-expired';
    protected $description = 'Deactivate tenants whose subscription has expired';

    public function handle()
    {
        $expiredTenants = Tenant::where('status', 1)
            ->whereNotNull('subscription_until')
            ->where('subscription_until', '<', now())
            ->get();

        foreach ($expiredTenants as $tenant) {
            $tenant->update(['status' => 0]);
            $this->info("Deactivated expired tenant: {$tenant->name} (ID: {$tenant->id})");
            Log::info("Deactivated expired tenant: {$tenant->name} (ID: {$tenant->id})");
        }

        $this->info("Processed {$expiredTenants->count()} expired tenants.");
        return 0;
    }
}