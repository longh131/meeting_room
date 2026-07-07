<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Department;
use App\Models\User;
use App\Services\IM\IMServiceFactory;
use App\Services\OrganizationSyncService;
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
                ->orderBy('sort_order')
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
    public function syncToLocal(Request $request, OrganizationSyncService $syncService)
    {
        try {
            $user = Auth::user();
            $result = $syncService->syncTenantToLocal($user->tenant_id);

            if (!$result['success']) {
                return response()->json(['error' => $result['message']], 400);
            }

            return response()->json([
                'success' => true,
                'message' => '同步完成',
                'data' => [
                    'departments_count' => $result['departments_count'],
                    'users_count' => $result['users_count'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[Organization] 同步到本地失败: ' . $e->getMessage());
            return response()->json(['error' => '同步失败: ' . $e->getMessage()], 500);
        }
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
                'sort_order' => $dept->sort_order,
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