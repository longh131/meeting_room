<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Services\IM\IMServiceFactory;
use Illuminate\Support\Facades\Log;

class OrganizationSyncService
{
    /**
     * 将指定租户的 IM 组织架构同步到本地数据库
     */
    public function syncTenantToLocal(int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return ['success' => false, 'message' => '租户不存在'];
        }

        $imService = IMServiceFactory::getEnabledService($tenantId);
        if (!$imService) {
            return ['success' => false, 'message' => '租户未启用IM服务'];
        }

        $departments = $imService->getDepartments(true);
        $deptCount = $this->syncDepartments($departments, $tenantId);
        $userCount = $this->syncUsers($departments, $tenantId, $tenant);

        Log::info("[OrganizationSync] 租户{$tenantId}同步完成: {$deptCount} 部门, {$userCount} 用户");

        return [
            'success' => true,
            'departments_count' => $deptCount,
            'users_count' => $userCount,
        ];
    }

    protected function syncDepartments(array $departments, int $tenantId): int
    {
        $count = 0;

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'im_department_id' => (string) ($dept['id'] ?? ''),
                ],
                [
                    'name' => $dept['name'] ?? '未命名部门',
                    'code' => 'im_' . $tenantId . '_' . ($dept['id'] ?? uniqid()),
                    'parent_id' => $this->findLocalParentId($dept['parent_id'] ?? null, $tenantId),
                    'sort_order' => $dept['order'] ?? 0,
                    'status' => true,
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function syncUsers(array $departments, int $tenantId, Tenant $tenant): int
    {
        $count = 0;
        $source = $tenant->dingtalk_enabled ? 'DINGTALK' : ($tenant->feishu_enabled ? 'FEISHU' : 'LOCAL');

        foreach ($departments as $dept) {
            $localDept = Department::where('tenant_id', $tenantId)
                ->where('im_department_id', (string) ($dept['id'] ?? ''))
                ->first();

            foreach ($dept['users'] ?? [] as $imUser) {
                $userIdField = $source === 'DINGTALK' ? 'dingtalk_user_id' : 'feishu_open_id';
                $imUserId = $imUser['userid'] ?? $imUser['open_id'] ?? $imUser['user_id'] ?? null;

                if (!$imUserId) {
                    continue;
                }

                User::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        $userIdField => $imUserId,
                    ],
                    [
                        'name' => $imUser['name'] ?? $imUser['username'] ?? '',
                        'email' => $imUser['email'] ?? null,
                        'phone' => $imUser['mobile'] ?? $imUser['phone'] ?? null,
                        'department_id' => $localDept?->id,
                        'position' => $imUser['position'] ?? $imUser['job_title'] ?? '',
                        'source' => $source,
                        'status' => true,
                    ]
                );

                $count++;
            }
        }

        return $count;
    }

    protected function findLocalParentId($imParentId, int $tenantId): ?int
    {
        if (empty($imParentId) || $imParentId == '0' || $imParentId == '1') {
            return null;
        }

        $parentDept = Department::where('tenant_id', $tenantId)
            ->where('im_department_id', (string) $imParentId)
            ->first();

        return $parentDept?->id;
    }
}
