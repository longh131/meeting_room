<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;
use App\Services\IM\IMServiceFactory;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    const CHANNEL_LOG = 'log';
    const CHANNEL_DINGTALK = 'dingtalk';
    const CHANNEL_FEISHU = 'feishu';
    const CHANNEL_EMAIL = 'email';

    const TYPE_RESERVATION_CREATE = 'reservation_create';
    const TYPE_RESERVATION_APPROVE = 'reservation_approve';
    const TYPE_RESERVATION_REJECT = 'reservation_reject';
    const TYPE_RESERVATION_CANCEL = 'reservation_cancel';
    const TYPE_RESERVATION_CHECKIN = 'reservation_checkin';
    const TYPE_RESERVATION_EXTEND = 'reservation_extend';
    const TYPE_APPROVAL_REMIND = 'approval_remind';

    /**
     * 发送通知
     *
     * @param int $userId 本地用户ID
     * @param string $type 通知类型
     * @param string $content 通知内容
     * @param string $channel 通知渠道
     * @return bool
     */
    public function send($userId, $type, $content, $channel = self::CHANNEL_LOG)
    {
        $log = NotificationLog::create([
            'channel' => $channel,
            'user_id' => $userId,
            'type' => $type,
            'content' => $content,
            'status' => 0,
        ]);

        try {
            $this->dispatch($channel, $userId, $type, $content);
            $log->status = 1;
            $log->save();
            return true;
        } catch (\Exception $e) {
            $log->status = 2;
            $log->error = $e->getMessage();
            $log->save();
            return false;
        }
    }

    /**
     * 根据用户注册来源发送通知（支持多租户）
     *
     * @param int $userId 本地用户ID
     * @param string $type 通知类型
     * @param string $content 通知内容
     * @return bool
     */
    public function sendByUserSource($userId, $type, $content)
    {
        $user = User::find($userId);
        if (!$user) {
            Log::error('[Notification] 用户不存在: ' . $userId);
            return false;
        }

        // 获取租户配置的通知渠道
        $tenantId = $user->tenant_id;
        $tenantChannel = IMServiceFactory::getNotificationChannel($tenantId);

        // 根据用户来源和租户配置决定通知渠道
        $channel = self::CHANNEL_LOG;
        
        // 如果租户配置了IM通知渠道，则使用租户配置
        if ($tenantChannel !== self::CHANNEL_LOG) {
            $channel = $tenantChannel;
        } elseif ($user->source === 'DINGTALK') {
            $channel = self::CHANNEL_DINGTALK;
        } elseif ($user->source === 'FEISHU') {
            $channel = self::CHANNEL_FEISHU;
        }

        return $this->send($userId, $type, $content, $channel);
    }

    /**
     * 调度通知到对应渠道
     */
    protected function dispatch($channel, $userId, $type, $content)
    {
        switch ($channel) {
            case self::CHANNEL_DINGTALK:
                $this->sendDingtalk($userId, $type, $content);
                break;
            case self::CHANNEL_FEISHU:
                $this->sendFeishu($userId, $type, $content);
                break;
            case self::CHANNEL_EMAIL:
                $this->sendEmail($userId, $type, $content);
                break;
            default:
                $this->sendLog($userId, $type, $content);
        }
    }

    /**
     * 日志通知（降级方案）
     */
    protected function sendLog($userId, $type, $content)
    {
        Log::info('[Notification] User: ' . $userId . ', Type: ' . $type . ', Content: ' . $content);
    }

    /**
     * 钉钉通知（支持多租户）
     */
    protected function sendDingtalk($userId, $type, $content)
    {
        try {
            $user = User::find($userId);
            if (!$user || empty($user->dingtalk_user_id)) {
                Log::warning('[Dingtalk Notification] 用户未绑定钉钉: ' . $userId);
                $this->sendLog($userId, $type, $content);
                return;
            }

            // 使用租户级别的钉钉服务
            $tenantId = $user->tenant_id;
            $imService = IMServiceFactory::createDingtalkService($tenantId);
            
            // 检查租户是否启用了钉钉服务
            if (!$imService->isEnabled()) {
                Log::warning('[Dingtalk Notification] 租户未启用钉钉服务: ' . $tenantId);
                $this->sendLog($userId, $type, $content);
                return;
            }

            $result = $imService->sendNotification($user->dingtalk_user_id, $content);

            if ($result) {
                Log::info('[Dingtalk Notification] 发送成功 - User: ' . $userId . ', Type: ' . $type);
            } else {
                Log::error('[Dingtalk Notification] 发送失败 - User: ' . $userId);
            }
        } catch (\Exception $e) {
            Log::error('[Dingtalk Notification] 异常: ' . $e->getMessage());
            $this->sendLog($userId, $type, $content);
        }
    }

    /**
     * 飞书通知（支持多租户）
     */
    protected function sendFeishu($userId, $type, $content)
    {
        try {
            $user = User::find($userId);
            if (!$user || empty($user->feishu_open_id)) {
                Log::warning('[Feishu Notification] 用户未绑定飞书: ' . $userId);
                $this->sendLog($userId, $type, $content);
                return;
            }

            // 使用租户级别的飞书服务
            $tenantId = $user->tenant_id;
            $imService = IMServiceFactory::createFeishuService($tenantId);
            
            // 检查租户是否启用了飞书服务
            if (!$imService->isEnabled()) {
                Log::warning('[Feishu Notification] 租户未启用飞书服务: ' . $tenantId);
                $this->sendLog($userId, $type, $content);
                return;
            }

            $result = $imService->sendNotification($user->feishu_open_id, $content);

            if ($result) {
                Log::info('[Feishu Notification] 发送成功 - User: ' . $userId . ', Type: ' . $type);
            } else {
                Log::error('[Feishu Notification] 发送失败 - User: ' . $userId);
            }
        } catch (\Exception $e) {
            Log::error('[Feishu Notification] 异常: ' . $e->getMessage());
            $this->sendLog($userId, $type, $content);
        }
    }

    /**
     * 邮件通知（预留）
     */
    protected function sendEmail($userId, $type, $content)
    {
        try {
            $user = User::find($userId);
            if (!$user || empty($user->email)) {
                Log::warning('[Email Notification] 用户未设置邮箱: ' . $userId);
                $this->sendLog($userId, $type, $content);
                return;
            }

            Log::info('[Email Notification] User: ' . $userId . ', Type: ' . $type . ', Content: ' . $content);
        } catch (\Exception $e) {
            Log::error('[Email Notification] 异常: ' . $e->getMessage());
            $this->sendLog($userId, $type, $content);
        }
    }

    /**
     * 格式化预定创建通知内容
     */
    public function formatReservationCreate($booking)
    {
        return "【会议室预定成功】\n会议主题: {$booking['title']}\n会议室: {$booking['room_name']}\n时间: {$booking['start_time']} ~ {$booking['end_time']}";
    }

    /**
     * 格式化审批通过通知内容
     */
    public function formatReservationApprove($booking)
    {
        return "【预定审批通过】\n会议主题: {$booking['title']}\n会议室: {$booking['room_name']}\n时间: {$booking['start_time']} ~ {$booking['end_time']}";
    }

    /**
     * 格式化审批驳回通知内容
     */
    public function formatReservationReject($booking, $reason = '')
    {
        $content = "【预定审批驳回】\n会议主题: {$booking['title']}";
        if (!empty($reason)) {
            $content .= "\n原因: {$reason}";
        }
        return $content;
    }

    /**
     * 格式化会前提醒通知内容
     */
    public function formatMeetingRemind($booking)
    {
        return "【会议即将开始】\n会议主题: {$booking['title']}\n会议室: {$booking['room_name']}\n时间: {$booking['start_time']}";
    }

    /**
     * 格式化签到提醒通知内容
     */
    public function formatCheckinRemind($booking)
    {
        return "【请签到】\n会议主题: {$booking['title']}\n会议室: {$booking['room_name']}\n请扫描会议室二维码完成签到";
    }
}