<?php

namespace App\Console\Commands;

use App\Services\WaitlistService;
use Illuminate\Console\Command;

class ExpireWaitlistOffers extends Command
{
    protected $signature = 'waitlist:expire';

    protected $description = '过期未确认的候补通知并顺延下一位';

    public function handle(WaitlistService $waitlistService)
    {
        $count = $waitlistService->expireStale();
        $this->info("共处理 {$count} 条过期候补");
        return 0;
    }
}
