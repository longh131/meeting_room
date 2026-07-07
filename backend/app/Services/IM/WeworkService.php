<?php

namespace App\Services\IM;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 企业微信IM服务实现
 * 支持多租户架构，每个租户独立配置企业微信应用
 */
class WeworkService extends AbstractIMService
{
    public function isEnabled(): bool
    {
        if (!$this->tenant) {
            return false;
        }
        return $this->tenant->wework_enabled && 
               !empty($this->tenant->wework_corp_id) && 
               !empty($this->tenant->wework_secret);
    }

    /**
     * 获取访问令牌（使用租户配置）
     */
    public function getAccessToken(): string
    {
        if (!$this->tenant) {
            throw new \Exception('未设置租户');
        }

        if ($cached = $this->getCachedToken()) {
            return $cached;
        }

        try {
            $response = Http::get('https://qyapi.weixin.qq.com/cgi-bin/gettoken', [
                'corpid' => $this->tenant->wework_corp_id,
                'corpsecret' => $this->tenant->wework_secret,
            ]);

            $result = $response->json();

            if ($result['errcode'] == 0) {
                $this->cacheToken($result['access_token'], $result['expires_in']);
                return $this->accessToken;
            }

            Log::error('[Wework] 租户' . $this->tenantId . '获取access_token失败: ' . ($result['errmsg'] ?? 'unknown'));
            throw new \Exception('获取企业微信access_token失败: ' . ($result['errmsg'] ?? 'unknown'));
        } catch (\Exception $e) {
            Log::error('[Wework] 租户' . $this->tenantId . '获取access_token异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 通过授权码获取用户信息
     */
    public function getUserByAuthCode(string $code): array
    {
        if (!$this->isEnabled()) {
            throw new \Exception('租户未启用企业微信服务');
        }

        try {
            $response = Http::get('https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo', [
                'access_token' => $this->getAccessToken(),
                'code' => $code,
            ]);

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Wework] 租户' . $this->tenantId . '获取用户信息失败: ' . ($result['errmsg'] ?? 'unknown'));
                throw new \Exception('获取用户信息失败: ' . ($result['errmsg'] ?? 'unknown'));
            }

            $userInfo = $this->getUserInfo($result['UserId']);

            return [
                'user_id' => $result['UserId'] ?? '',
                'name' => $userInfo['name'] ?? '',
                'email' => $userInfo['email'] ?? '',
                'phone' => $userInfo['mobile'] ?? '',
                'department_id' => $userInfo['department'][0] ?? null,
                'position' => $userInfo['position'] ?? '',
                'avatar' => $userInfo['avatar'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('[Wework] 租户' . $this->tenantId . 'getUserByAuthCode异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取用户详情
     */
    public function getUserInfo(string $userId): array
    {
        try {
            $response = Http::get('https://qyapi.weixin.qq.com/cgi-bin/user/get', [
                'access_token' => $this->getAccessToken(),
                'userid' => $userId,
            ]);

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Wework] 租户' . $this->tenantId . '获取用户详情失败: ' . ($result['errmsg'] ?? 'unknown'));
                return [];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('[Wework] 租户' . $this->tenantId . 'getUserInfo异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 同步组织架构
     */
    public function syncOrganization(bool $fullSync = false): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('[Wework] 租户' . $this->tenantId . '未启用企业微信服务');
            return false;
        }

        try {
            $departments = $this->getDepartments(true);
            Log::info('[Wework] 租户' . $this->tenantId . '同步组织架构完成，获取到 ' . count($departments) . ' 个部门');
            return true;
        } catch (\Exception $e) {
            Log::error('[Wework] 租户' . $this->tenantId . '同步组织架构失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取部门列表
     */
    public function getDepartments(bool $withUsers = false): array
    {
        try {
            $response = Http::get('https://qyapi.weixin.qq.com/cgi-bin/department/list', [
                'access_token' => $this->getAccessToken(),
                'id' => 1,
            ]);

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Wework] 租户' . $this->tenantId . '获取部门列表失败: ' . ($result['errmsg'] ?? 'unknown'));
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
            Log::error('[Wework] 租户' . $this->tenantId . 'getDepartments异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取部门下的用户
     */
    protected function getUsersByDepartment(int $departmentId): array
    {
        try {
            $response = Http::get('https://qyapi.weixin.qq.com/cgi-bin/user/simplelist', [
                'access_token' => $this->getAccessToken(),
                'department_id' => $departmentId,
                'fetch_child' => 1,
            ]);

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Wework] 租户' . $this->tenantId . '获取部门用户失败: ' . ($result['errmsg'] ?? 'unknown'));
                return [];
            }

            return $result['userlist'] ?? [];
        } catch (\Exception $e) {
            Log::error('[Wework] 租户' . $this->tenantId . 'getUsersByDepartment异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 发起审批
     */
    public function sendApproval(array $booking): array
    {
        if (!$this->isEnabled()) {
            throw new \Exception('租户未启用企业微信服务');
        }

        try {
            $formItems = [
                ['key' => '会议主题', 'value' => $booking['title'] ?? ''],
                ['key' => '会议室', 'value' => $booking['room_name'] ?? ''],
                ['key' => '开始时间', 'value' => $booking['start_time'] ?? ''],
                ['key' => '结束时间', 'value' => $booking['end_time'] ?? ''],
                ['key' => '预定人', 'value' => $booking['user_name'] ?? ''],
            ];

            $data = [
                'agentid' => $this->tenant->wework_agent_id,
                'template_id' => $this->tenant->wework_approval_code,
                'creator_userid' => $booking['wework_user_id'] ?? '',
                'form_item_list' => $formItems,
            ];

            $response = Http::post(
                'https://qyapi.weixin.qq.com/cgi-bin/oa/applyevent/create',
                $data,
                ['headers' => ['Content-Type' => 'application/json']]
            );

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Wework] 租户' . $this->tenantId . '发起审批失败: ' . ($result['errmsg'] ?? 'unknown'));
                throw new \Exception('发起审批失败: ' . ($result['errmsg'] ?? 'unknown'));
            }

            return [
                'success' => true,
                'process_instance_id' => $result['apply_id'],
                'message' => '审批已发起',
            ];
        } catch (\Exception $e) {
            Log::error('[Wework] 租户' . $this->tenantId . 'sendApproval异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 处理审批回调
     */
    public function handleApprovalCallback(array $data): array
    {
        try {
            $applyId = $data['ApplyId'] ?? '';
            $status = $data['Status'] ?? '';

            $statusMap = [
                '1' => 'approved',
                '2' => 'rejected',
                '0' => 'pending',
            ];

            return [
                'success' => true,
                'process_instance_id' => $applyId,
                'status' => $statusMap[$status] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            Log::error('[Wework] 租户' . $this->tenantId . 'handleApprovalCallback异常: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 发送消息通知
     */
    public function sendNotification(string $userId, string $message, string $type = 'text'): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('[Wework] 租户' . $this->tenantId . '未启用企业微信服务');
            return false;
        }

        try {
            $data = [
                'touser' => $userId,
                'msgtype' => $type,
                'agentid' => $this->tenant->wework_agent_id,
                $type => ['content' => $message],
            ];

            $response = Http::post(
                'https://qyapi.weixin.qq.com/cgi-bin/message/send',
                $data,
                ['headers' => ['Content-Type' => 'application/json']]
            );

            $result = $response->json();

            if ($result['errcode'] != 0) {
                Log::error('[Wework] 租户' . $this->tenantId . '发送消息失败: ' . ($result['errmsg'] ?? 'unknown'));
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('[Wework] 租户' . $this->tenantId . 'sendNotification异常: ' . $e->getMessage());
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
            $ticketResponse = Http::get('https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket', [
                'access_token' => $this->getAccessToken(),
            ]);

            $ticketResult = $ticketResponse->json();

            if ($ticketResult['errcode'] != 0) {
                Log::error('[Wework] 租户' . $this->tenantId . '获取jsapi_ticket失败: ' . ($ticketResult['errmsg'] ?? 'unknown'));
                return [];
            }

            $ticket = $ticketResult['ticket'];
            $nonceStr = uniqid();
            $timestamp = time();

            $signature = $this->generateSignature($ticket, $nonceStr, $timestamp, $url);

            return [
                'corpId' => $this->tenant->wework_corp_id,
                'agentId' => $this->tenant->wework_agent_id,
                'nonceStr' => $nonceStr,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'ticket' => $ticket,
            ];
        } catch (\Exception $e) {
            Log::error('[Wework] 租户' . $this->tenantId . 'getJsApiConfig异常: ' . $e->getMessage());
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