<?php

namespace App\Console\Commands;

use App\Services\IM\IMServiceFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOrganization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organization:sync {--full}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步组织架构（钉钉/飞书）';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $fullSync = $this->option('full');

            $this->info('开始同步组织架构...');

            $imService = IMServiceFactory::getService();
            $result = $imService->syncOrganization($fullSync);

            if ($result) {
                $this->info('组织架构同步成功！');
                Log::info('[Cron] 组织架构同步成功');
                return 0;
            }

            $this->error('组织架构同步失败');
            Log::error('[Cron] 组织架构同步失败');
            return 1;
        } catch (\Exception $e) {
            $this->error('同步失败: ' . $e->getMessage());
            Log::error('[Cron] 组织架构同步异常: ' . $e->getMessage());
            return 1;
        }
    }
}