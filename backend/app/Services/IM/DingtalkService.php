<?php

namespace App\Services\IM;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 钉钉IM服务实现
 * 支持多租户架构，每个租户独立配置钉钉应用
 */
class DingtalkService implements IMService
{
    protected $tenantId;
    protected $tenant;
    protected $accessToken;
    protected $tokenExpiresAt;

    /**
     * 设置当前租户
     */
    public function setTenant(int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->tenant = Tenant::find($tenantId);
        $this->accessToken = null;
        $this->tokenExpiresAt = 0;
    }

    /**
     * 获取当前租户ID
     */
    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    /**
     * 检查租户是否启用了钉钉服务
     */
    public function isEnabled(): bool
    {
        if (!$this->tenant) {
            return false;
        }
        return $this->tenant->dingtalk_enabled && 
               !empty($this->tenant->dingtalk_app_key) && 
               !empty($this->tenant->dingtalk_app_secret);
    }

    /**
     * 获取访问令牌（使用租户配置）
     */
    public function getAccessToken(): string
    {
        if (!$this->tenant) {
            throw new \Exception('未设置租户');
        }

        if ($this->accessToken && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        try {
            $response = Http::get('https://oapi.dingtalk.com/gettoken', [
                'appkey' => $this->tenant->dingtalk_app_key,
                'appsecret' => $this->tenant->dingtalk_app_secret,
            ]);

            $result = $response->json();

            if ($result['errcode'] == 0) {
                $this->accessToken = $result['access_token'];
                $this->tokenExpiresAt = time() + $result['expires_in'] - 60;
                return $this->accessToken;
            }

            Log::error('[Dingtalk] 租户' . $this->tenantId . '获取access_token失败: ' . $result['errmsg']);
            throw new \Exception('获取钉钉access_token失败: ' . $result['errmsg']);
        } catch (\Exception $e) {
            Log::error('[Dingtalk] 租户' . $this->tenantId . '获取access_token异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 通过授权码获取用户信息
     */
    public function getUserByAuthCode(string $code): array
    {
        if (!$this->isEnabled()) {
            throw new \Exception('租户未启用钉钉服务');
        }

        try {
            $response = Http::get('https://oapi.dingtalk.com/user/getuserinfo', [
                'access_token' => $this->getAccessToken(),
                'code' => $code,
            ]);

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Dingtalk] 租户' . $this->tenantId . '获取用户信息失败: ' . $result['errmsg']);
                throw new \Exception('获取用户信息失败: ' . $result['errmsg']);
            }

            $userInfo = $this->getUserInfo($result['userid']);

            return [
                'user_id' => $result['userid'],
                'name' => $userInfo['name'] ?? '',
                'email' => $userInfo['email'] ?? '',
                'phone' => $userInfo['mobile'] ?? '',
                'department_id' => $userInfo['department'][0] ?? null,
                'position' => $userInfo['position'] ?? '',
                'avatar' => $userInfo['avatar'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('[Dingtalk] 租户' . $this->tenantId . 'getUserByAuthCode异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取用户详情
     */
    public function getUserInfo(string $userId): array
    {
        try {
            $response = Http::get('https://oapi.dingtalk.com/user/get', [
                'access_token' => $this->getAccessToken(),
                'userid' => $userId,
            ]);

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Dingtalk] 租户' . $this->tenantId . '获取用户详情失败: ' . $result['errmsg']);
                return [];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('[Dingtalk] 租户' . $this->tenantId . 'getUserInfo异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 同步组织架构
     */
    public function syncOrganization(bool $fullSync = false): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('[Dingtalk] 租户' . $this->tenantId . '未启用钉钉服务');
            return false;
        }

        try {
            $departments = $this->getDepartments(true);
            Log::info('[Dingtalk] 租户' . $this->tenantId . '同步组织架构完成，获取到 ' . count($departments) . ' 个部门');
            return true;
        } catch (\Exception $e) {
            Log::error('[Dingtalk] 租户' . $this->tenantId . '同步组织架构失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取部门列表
     */
    public function getDepartments(bool $withUsers = false): array
    {
        try {
            $response = Http::get('https://oapi.dingtalk.com/department/list', [
                'access_token' => $this->getAccessToken(),
                'id' => 1,
                'fetch_child' => 'true',
            ]);

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Dingtalk] 租户' . $this->tenantId . '获取部门列表失败: ' . $result['errmsg']);
                return [];
            }

            $departments = [];
            foreach ($result['department'] as $dept) {
                $department = [
                    'id' => $dept['id'],
                    'name' => $dept['name'],
                    'parent_id' => $dept['parentid'] ?? 0,
                    'order' => $dept['order'] ?? 0,
                    'users' => [],
                ];

                if ($withUsers) {
                    $department['users'] = $this->getUsersByDepartment($dept['id']);
                }

                $departments[] = $department;
            }

            return $departments;
        } catch (\Exception $e) {
            Log::error('[Dingtalk] 租户' . $this->tenantId . 'getDepartments异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取部门下的用户
     */
    protected function getUsersByDepartment(int $departmentId): array
    {
        try {
            $response = Http::get('https://oapi.dingtalk.com/user/simplelist', [
                'access_token' => $this->getAccessToken(),
                'department_id' => $departmentId,
                'offset' => 0,
                'size' => 100,
            ]);

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Dingtalk] 租户' . $this->tenantId . '获取部门用户失败: ' . $result['errmsg']);
                return [];
            }

            return $result['userlist'] ?? [];
        } catch (\Exception $e) {
            Log::error('[Dingtalk] 租户' . $this->tenantId . 'getUsersByDepartment异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 发起审批
     */
    public function sendApproval(array $booking): array
    {
        if (!$this->isEnabled()) {
            throw new \Exception('租户未启用钉钉服务');
        }

        try {
            $formComponentValues = [
                ['name' => '会议主题', 'value' => $booking['title'] ?? ''],
                ['name' => '会议室', 'value' => $booking['room_name'] ?? ''],
                ['name' => '开始时间', 'value' => $booking['start_time'] ?? ''],
                ['name' => '结束时间', 'value' => $booking['end_time'] ?? ''],
                ['name' => '预定人', 'value' => $booking['user_name'] ?? ''],
            ];

            $data = [
                'agent_id' => $this->tenant->dingtalk_agent_id,
                'process_code' => $this->tenant->dingtalk_process_code,
                'originator_user_id' => $booking['dingtalk_user_id'] ?? '',
                'dept_id' => $booking['department_id'] ?? '',
                'form_component_values' => $formComponentValues,
            ];

            $response = Http::post(
                'https://oapi.dingtalk.com/topapi/processinstance/create',
                $data,
                ['headers' => ['Content-Type' => 'application/json']]
            );

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Dingtalk] 租户' . $this->tenantId . '发起审批失败: ' . $result['errmsg']);
                throw new \Exception('发起审批失败: ' . $result['errmsg']);
            }

            return [
                'success' => true,
                'process_instance_id' => $result['result']['process_instance_id'],
                'message' => '审批已发起',
            ];
        } catch (\Exception $e) {
            Log::error('[Dingtalk] 租户' . $this->tenantId . 'sendApproval异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 处理审批回调
     */
    public function handleApprovalCallback(array $data): array
    {
        try {
            $processInstanceId = $data['processInstanceId'] ?? '';
            $status = $data['status'] ?? '';

            $statusMap = [
                'COMPLETED' => 'approved',
                'TERMINATED' => 'rejected',
                'RUNNING' => 'pending',
            ];

            return [
                'success' => true,
                'process_instance_id' => $processInstanceId,
                'status' => $statusMap[$status] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            Log::error('[Dingtalk] 租户' . $this->tenantId . 'handleApprovalCallback异常: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 发送消息通知
     */
    public function sendNotification(string $userId, string $message, string $type = 'text'): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('[Dingtalk] 租户' . $this->tenantId . '未启用钉钉服务');
            return false;
        }

        try {
            $data = [
                'agent_id' => $this->tenant->dingtalk_agent_id,
                'userid_list' => $userId,
                'msgtype' => $type,
                $type => ['content' => $message],
            ];

            $response = Http::post(
                'https://oapi.dingtalk.com/topapi/message/corpconversation/asyncsend_v2',
                $data,
                ['headers' => ['Content-Type' => 'application/json']]
            );

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Dingtalk] 租户' . $this->tenantId . '发送消息失败: ' . $result['errmsg']);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('[Dingtalk] 租户' . $this->tenantId . 'sendNotification异常: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取前端JSAPI配置
     */
    public function getJsApiConfig(string $url): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $ticketResponse = Http::get('https://oapi.dingtalk.com/get_jsapi_ticket', [
                'access_token' => $this->getAccessToken(),
            ]);

            $ticketResult = $ticketResponse->json();

            if ($ticketResult['errcode'] != 0) {
                Log::error('[Dingtalk] 租户' . $this->tenantId . '获取jsapi_ticket失败: ' . $ticketResult['errmsg']);
                return [];
            }

            $ticket = $ticketResult['ticket'];
            $nonceStr = uniqid();
            $timestamp = time();

            $signature = $this->generateSignature($ticket, $nonceStr, $timestamp, $url);

            return [
                'corpId' => $this->tenant->dingtalk_corp_id,
                'agentId' => $this->tenant->dingtalk_agent_id,
                'nonceStr' => $nonceStr,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'ticket' => $ticket,
            ];
        } catch (\Exception $e) {
            Log::error('[Dingtalk] 租户' . $this->tenantId . 'getJsApiConfig异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 生成签名
     */
    protected function generateSignature(string $ticket, string $nonceStr, int $timestamp, string $url): string
    {
        $plainText = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        return sha1($plainText);
    }
}