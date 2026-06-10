<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Department;
use App\Models\User;
use App\Services\IM\IMServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * 组织架构同步控制器
 * 支持多租户架构，每个租户独立同步组织架构
 */
class OrganizationController extends Controller
{
    /**
     * 获取部门树结构
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDepartmentTree(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // 从本地数据库获取部门树
            $departments = Department::where('tenant_id', $tenantId)
                ->orderBy('parent_id')
                ->orderBy('order')
                ->get();

            $tree = $this->buildDepartmentTree($departments);

            return response()->json([
                'success' => true,
                'data' => $tree,
            ]);
        } catch (\Exception $e) {
            Log::error('[Organization] 获取部门树失败: ' . $e->getMessage());
            return response()->json(['error' => '获取部门树失败'], 500);
        }
    }

    /**
     * 获取部门用户列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsersByDepartment(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $departmentId = $request->input('department_id');

            $users = User::where('tenant_id', $tenantId)
                ->where('department_id', $departmentId)
                ->where('status', true)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            Log::error('[Organization] 获取部门用户失败: ' . $e->getMessage());
            return response()->json(['error' => '获取部门用户失败'], 500);
        }
    }

    /**
     * 同步组织架构（从IM平台）
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sync(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $fullSync = $request->input('full_sync', false);

            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return response()->json(['error' => '租户不存在'], 404);
            }

            $imService = IMServiceFactory::getEnabledService($tenantId);
            
            if (!$imService) {
                return response()->json(['error' => '租户未启用IM服务'], 400);
            }

            $result = $imService->syncOrganization($fullSync);

            return response()->json([
                'success' => $result,
                'message' => $result ? '同步成功' : '同步失败',
            ]);
        } catch (\Exception $e) {
            Log::error('[Organization] 同步失败: ' . $e->getMessage());
            return response()->json(['error' => '同步失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 全量同步到本地数据库
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncToLocal(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return response()->json(['error' => '租户不存在'], 404);
            }

            $imService = IMServiceFactory::getEnabledService($tenantId);
            
            if (!$imService) {
                return response()->json(['error' => '租户未启用IM服务'], 400);
            }

            // 获取IM平台的部门树（包含用户）
            $departments = $imService->getDepartments(true);

            // 同步部门
            $deptCount = $this->syncDepartmentsToLocal($departments, $tenantId);

            // 同步用户
            $userCount = $this->syncUsersToLocal($departments, $tenantId);

            return response()->json([
                'success' => true,
                'message' => '同步完成',
                'data' => [
                    'departments_count' => $deptCount,
                    'users_count' => $userCount,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[Organization] 同步到本地失败: ' . $e->getMessage());
            return response()->json(['error' => '同步失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 同步部门到本地数据库
     *
     * @param array $departments IM部门列表
     * @param int $tenantId 租户ID
     * @return int 同步数量
     */
    protected function syncDepartmentsToLocal(array $departments, int $tenantId): int
    {
        $count = 0;

        foreach ($departments as $dept) {
            $localDept = Department::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'im_department_id' => $dept['id'],
                ],
                [
                    'name' => $dept['name'],
                    'parent_id' => $this->findLocalParentId($dept['parent_id'], $tenantId),
                    'order' => $dept['order'] ?? 0,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * 同步用户到本地数据库
     *
     * @param array $departments IM部门列表（包含用户）
     * @param int $tenantId 租户ID
     * @return int 同步数量
     */
    protected function syncUsersToLocal(array $departments, int $tenantId): int
    {
        $count = 0;
        $source = IMServiceFactory::getEnabledService($tenantId)->getTenantId() ? 
                  ($tenant->dingtalk_enabled ? 'DINGTALK' : 'FEISHU') : 'LOCAL';

        $tenant = Tenant::find($tenantId);
        $source = $tenant->dingtalk_enabled ? 'DINGTALK' : ($tenant->feishu_enabled ? 'FEISHU' : 'LOCAL');

        foreach ($departments as $dept) {
            $localDept = Department::where('tenant_id', $tenantId)
                ->where('im_department_id', $dept['id'])
                ->first();

            foreach ($dept['users'] as $imUser) {
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
                        'department_id' => $localDept ? $localDept->id : null,
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

    /**
     * 查找本地父部门ID
     *
     * @param string $imParentId IM父部门ID
     * @param int $tenantId 租户ID
     * @return int|null
     */
    protected function findLocalParentId(string $imParentId, int $tenantId): ?int
    {
        if (empty($imParentId) || $imParentId == '0' || $imParentId == '1') {
            return null;
        }

        $parentDept = Department::where('tenant_id', $tenantId)
            ->where('im_department_id', $imParentId)
            ->first();

        return $parentDept ? $parentDept->id : null;
    }

    /**
     * 构建部门树结构
     *
     * @param \Illuminate\Support\Collection $departments
     * @return array
     */
    protected function buildDepartmentTree($departments): array
    {
        $tree = [];
        $map = [];

        foreach ($departments as $dept) {
            $map[$dept->id] = [
                'id' => $dept->id,
                'name' => $dept->name,
                'parent_id' => $dept->parent_id,
                'order' => $dept->order,
                'children' => [],
            ];
        }

        foreach ($map as $id => $node) {
            if ($node['parent_id'] && isset($map[$node['parent_id']])) {
                $map[$node['parent_id']]['children'][] = $node;
            } else {
                $tree[] = $node;
            }
        }

        return $tree;
    }
}