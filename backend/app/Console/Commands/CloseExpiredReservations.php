<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CloseExpiredReservations extends Command
{
    protected $signature = 'reservations:close-expired';

    protected $description = '自动关闭已过期的会议';

    public function handle()
    {
        $totalCount = 0;

        $tenants = Tenant::where('status', 1)->get();

        foreach ($tenants as $tenant) {
            $count = Reservation::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', [Reservation::STATUS_RESERVED, Reservation::STATUS_CHECKIN])
                ->where('end_time', '<', now())
                ->update(['status' => Reservation::STATUS_ENDED]);

            if ($count > 0) {
                $this->info("租户 [{$tenant->name}] 已自动关闭 {$count} 个过期会议");
                $totalCount += $count;
            }
        }

        $this->info("总共已自动关闭 {$totalCount} 个过期会议");

        return 0;
    }
}
