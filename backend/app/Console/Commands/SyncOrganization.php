<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\OrganizationSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOrganization extends Command
{
    protected $signature = 'organization:sync {--full}';

    protected $description = '同步组织架构（钉钉/飞书）到本地数据库';

    public function handle(OrganizationSyncService $syncService)
    {
        $fullSync = $this->option('full');
        $this->info('开始同步组织架构...');

        $tenants = Tenant::where('status', true)->get();
        $successCount = 0;
        $failCount = 0;

        foreach ($tenants as $tenant) {
            if (!$tenant->dingtalk_enabled && !$tenant->feishu_enabled) {
                continue;
            }

            $this->info("同步租户: {$tenant->name} (ID: {$tenant->id})");

            try {
                $result = $syncService->syncTenantToLocal($tenant->id);

                if ($result['success']) {
                    $successCount++;
                    $this->info("  完成: {$result['departments_count']} 部门, {$result['users_count']} 用户");
                } else {
                    $failCount++;
                    $this->error("  失败: {$result['message']}");
                }
            } catch (\Exception $e) {
                $failCount++;
                $this->error("  异常: {$e->getMessage()}");
                Log::error('[Cron] 组织架构同步异常: ' . $e->getMessage());
            }
        }

        if ($fullSync) {
            Log::info("[Cron] 全量组织架构同步完成: 成功{$successCount}, 失败{$failCount}");
        }

        if ($successCount === 0 && $failCount === 0) {
            $this->warn('没有需要同步的租户');
            return 0;
        }

        $this->info("同步完成: 成功 {$successCount}, 失败 {$failCount}");
        return $failCount > 0 ? 1 : 0;
    }
}
