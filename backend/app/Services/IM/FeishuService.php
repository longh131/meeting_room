<?php

namespace App\Services\IM;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 飞书IM服务实现
 * 支持多租户架构，每个租户独立配置飞书应用
 */
class FeishuService implements IMService
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
     * 检查租户是否启用了飞书服务
     */
    public function isEnabled(): bool
    {
        if (!$this->tenant) {
            return false;
        }
        return $this->tenant->feishu_enabled && 
               !empty($this->tenant->feishu_app_id) && 
               !empty($this->tenant->feishu_app_secret);
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
            $response = Http::post('https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal', [
                'app_id' => $this->tenant->feishu_app_id,
                'app_secret' => $this->tenant->feishu_app_secret,
            ]);

            $result = $response->json();

            if ($result['code'] != 0) {
                Log::error('[Feishu] 租户' . $this->tenantId . '获取tenant_access_token失败: ' . $result['msg']);
                throw new \Exception('获取飞书tenant_access_token失败: ' . $result['msg']);
            }

            $this->accessToken = $result['tenant_access_token'];
            $this->tokenExpiresAt = time() + $result['expire'] - 60;
            return $this->accessToken;
        } catch (\Exception $e) {
            Log::error('[Feishu] 租户' . $this->tenantId . '获取tenant_access_token异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 通过授权码获取用户信息
     */
    public function getUserByAuthCode(string $code): array
    {
        if (!$this->isEnabled()) {
            throw new \Exception('租户未启用飞书服务');
        }

        try {
            $tokenResponse = Http::post('https://open.feishu.cn/open-apis/authen/v1/access_token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

            $tokenResult = $tokenResponse->json();

            if ($tokenResult['code'] != 0) {
                Log::error('[Feishu] 租户' . $this->tenantId . '获取用户access_token失败: ' . $tokenResult['msg']);
                throw new \Exception('获取用户access_token失败: ' . $tokenResult['msg']);
            }

            $userAccessToken = $tokenResult['data']['access_token'];

            $userResponse = Http::get('https://open.feishu.cn/open-apis/authen/v1/user_info', [
                'headers' => ['Authorization' => 'Bearer ' . $userAccessToken],
            ]);

            $userResult = $userResponse->json();

            if ($userResult['code'] != 0) {
                Log::error('[Feishu] 租户' . $this->tenantId . '获取用户信息失败: ' . $userResult['msg']);
                throw new \Exception('获取用户信息失败: ' . $userResult['msg']);
            }

            $userInfo = $userResult['data'];

            return [
                'user_id' => $userInfo['open_id'] ?? '',
                'name' => $userInfo['name'] ?? '',
                'email' => $userInfo['email'] ?? '',
                'phone' => $userInfo['mobile'] ?? '',
                'department_id' => $userInfo['department_id'] ?? null,
                'position' => $userInfo['position'] ?? '',
                'avatar' => $userInfo['avatar_url'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('[Feishu] 租户' . $this->tenantId . 'getUserByAuthCode异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取用户详情
     */
    public function getUserInfo(string $userId): array
    {
        try {
            $response = Http::get('https://open.feishu.cn/open-apis/contact/v3/users/' . $userId, [
                'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken()],
            ]);

            $result = $response->json();

            if ($result['code'] != 0) {
                Log::error('[Feishu] 租户' . $this->tenantId . '获取用户详情失败: ' . $result['msg']);
                return [];
            }

            return $result['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('[Feishu] 租户' . $this->tenantId . 'getUserInfo异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 同步组织架构
     */
    public function syncOrganization(bool $fullSync = false): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('[Feishu] 租户' . $this->tenantId . '未启用飞书服务');
            return false;
        }

        try {
            $departments = $this->getDepartments(true);
            Log::info('[Feishu] 租户' . $this->tenantId . '同步组织架构完成，获取到 ' . count($departments) . ' 个部门');
            return true;
        } catch (\Exception $e) {
            Log::error('[Feishu] 租户' . $this->tenantId . '同步组织架构失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取部门列表
     */
    public function getDepartments(bool $withUsers = false): array
    {
        try {
            $response = Http::get('https://open.feishu.cn/open-apis/contact/v3/departments', [
                'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken()],
                'query' => ['page_size' => 100],
            ]);

            $result = $response->json();

            if ($result['code'] != 0) {
                Log::error('[Feishu] 租户' . $this->tenantId . '获取部门列表失败: ' . $result['msg']);
                return [];
            }

            $departments = [];
            foreach ($result['data']['items'] as $dept) {
                $department = [
                    'id' => $dept['department_id'],
                    'name' => $dept['name'],
                    'parent_id' => $dept['parent_department_id'] ?? 0,
                    'order' => $dept['order'] ?? 0,
                    'users' => [],
                ];

                if ($withUsers) {
                    $department['users'] = $this->getUsersByDepartment($dept['department_id']);
                }

                $departments[] = $department;
            }

            return $departments;
        } catch (\Exception $e) {
            Log::error('[Feishu] 租户' . $this->tenantId . 'getDepartments异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取部门下的用户
     */
    protected function getUsersByDepartment(string $departmentId): array
    {
        try {
            $response = Http::get('https://open.feishu.cn/open-apis/contact/v3/users', [
                'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken()],
                'query' => [
                    'department_id' => $departmentId,
                    'page_size' => 100,
                ],
            ]);

            $result = $response->json();

            if ($result['code'] != 0) {
                Log::error('[Feishu] 租户' . $this->tenantId . '获取部门用户失败: ' . $result['msg']);
                return [];
            }

            return $result['data']['items'] ?? [];
        } catch (\Exception $e) {
            Log::error('[Feishu] 租户' . $this->tenantId . 'getUsersByDepartment异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 发起审批
     */
    public function sendApproval(array $booking): array
    {
        if (!$this->isEnabled()) {
            throw new \Exception('租户未启用飞书服务');
        }

        try {
            $formFields = [
                ['key' => 'meeting_title', 'value' => $booking['title'] ?? ''],
                ['key' => 'meeting_room', 'value' => $booking['room_name'] ?? ''],
                ['key' => 'start_time', 'value' => $booking['start_time'] ?? ''],
                ['key' => 'end_time', 'value' => $booking['end_time'] ?? ''],
                ['key' => 'applicant', 'value' => $booking['user_name'] ?? ''],
            ];

            $data = [
                'approval_code' => $this->tenant->feishu_approval_code,
                'originator_user_id' => $booking['feishu_open_id'] ?? '',
                'form' => ['fields' => $formFields],
            ];

            $response = Http::post(
                'https://open.feishu.cn/open-apis/approval/v4/instances/create',
                $data,
                ['headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken()]]
            );

            $result = $response->json();

            if ($result['code'] != 0) {
                Log::error('[Feishu] 租户' . $this->tenantId . '发起审批失败: ' . $result['msg']);
                throw new \Exception('发起审批失败: ' . $result['msg']);
            }

            return [
                'success' => true,
                'process_instance_id' => $result['data']['instance_code'],
                'message' => '审批已发起',
            ];
        } catch (\Exception $e) {
            Log::error('[Feishu] 租户' . $this->tenantId . 'sendApproval异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 处理审批回调
     */
    public function handleApprovalCallback(array $data): array
    {
        try {
            $instanceCode = $data['instance_code'] ?? '';
            $status = $data['status'] ?? '';

            $statusMap = [
                'APPROVED' => 'approved',
                'REJECTED' => 'rejected',
                'PENDING' => 'pending',
                'CANCELED' => 'rejected',
            ];

            return [
                'success' => true,
                'process_instance_id' => $instanceCode,
                'status' => $statusMap[$status] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            Log::error('[Feishu] 租户' . $this->tenantId . 'handleApprovalCallback异常: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 发送消息通知
     */
    public function sendNotification(string $userId, string $message, string $type = 'text'): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('[Feishu] 租户' . $this->tenantId . '未启用飞书服务');
            return false;
        }

        try {
            $data = [
                'open_id' => $userId,
                'msg_type' => $type,
                'content' => json_encode([$type => ['text' => $message]]),
            ];

            $response = Http::post(
                'https://open.feishu.cn/open-apis/message/v4/send',
                $data,
                ['headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken()]]
            );

            $result = $response->json();

            if ($result['code'] != 0) {
                Log::error('[Feishu] 租户' . $this->tenantId . '发送消息失败: ' . $result['msg']);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('[Feishu] 租户' . $this->tenantId . 'sendNotification异常: ' . $e->getMessage());
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
            $ticketResponse = Http::get('https://open.feishu.cn/open-apis/jssdk/ticket/get', [
                'headers' => ['Authorization' => 'Bearer ' . $this->getAccessToken()],
            ]);

            $ticketResult = $ticketResponse->json();

            if ($ticketResult['code'] != 0) {
                Log::error('[Feishu] 租户' . $this->tenantId . '获取jsapi_ticket失败: ' . $ticketResult['msg']);
                return [];
            }

            $ticket = $ticketResult['data']['ticket'];
            $nonceStr = uniqid();
            $timestamp = time();

            $signature = $this->generateSignature($ticket, $nonceStr, $timestamp, $url);

            return [
                'appId' => $this->tenant->feishu_app_id,
                'nonceStr' => $nonceStr,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'ticket' => $ticket,
            ];
        } catch (\Exception $e) {
            Log::error('[Feishu] 租户' . $this->tenantId . 'getJsApiConfig异常: ' . $e->getMessage());
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