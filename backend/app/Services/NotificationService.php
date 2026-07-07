<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\IM\IMServiceFactory;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected TemplateService $templateService;

    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    const CHANNEL_LOG = 'log';
    const CHANNEL_DINGTALK = 'dingtalk';
    const CHANNEL_FEISHU = 'feishu';
    const CHANNEL_WEWORK = 'wework';
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
        if (config('queue.default') !== 'sync') {
            SendNotificationJob::dispatch($userId, $type, $content, $channel);
            return true;
        }

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
        } elseif ($user->source === 'WEWORK') {
            $channel = self::CHANNEL_WEWORK;
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
            case self::CHANNEL_WEWORK:
                $this->sendWework($userId, $type, $content);
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
     * 企业微信通知（支持多租户）
     */
    protected function sendWework($userId, $type, $content)
    {
        try {
            $user = User::find($userId);
            if (!$user || empty($user->wework_user_id)) {
                Log::warning('[Wework Notification] 用户未绑定企业微信: ' . $userId);
                $this->sendLog($userId, $type, $content);
                return;
            }

            $tenantId = $user->tenant_id;
            $imService = IMServiceFactory::createWeworkService($tenantId);

            if (!$imService->isEnabled()) {
                Log::warning('[Wework Notification] 租户未启用企业微信服务: ' . $tenantId);
                $this->sendLog($userId, $type, $content);
                return;
            }

            $result = $imService->sendNotification($user->wework_user_id, $content);

            if ($result) {
                Log::info('[Wework Notification] 发送成功 - User: ' . $userId . ', Type: ' . $type);
            } else {
                Log::error('[Wework Notification] 发送失败 - User: ' . $userId);
            }
        } catch (\Exception $e) {
            Log::error('[Wework Notification] 异常: ' . $e->getMessage());
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
    public function formatReservationCreate($booking, ?int $tenantId = null)
    {
        return $this->templateService->render($tenantId, self::TYPE_RESERVATION_CREATE, $booking);
    }

    /**
     * 格式化审批通过通知内容
     */
    public function formatReservationApprove($booking, ?int $tenantId = null)
    {
        return $this->templateService->render($tenantId, self::TYPE_RESERVATION_APPROVE, $booking);
    }

    /**
     * 格式化审批驳回通知内容
     */
    public function formatReservationReject($booking, $reason = '', ?int $tenantId = null)
    {
        $vars = array_merge($booking, ['reason' => $reason]);
        return $this->templateService->render($tenantId, self::TYPE_RESERVATION_REJECT, $vars);
    }

    /**
     * 格式化会前提醒通知内容
     */
    public function formatMeetingRemind($booking, ?int $tenantId = null)
    {
        return $this->templateService->render($tenantId, 'meeting_remind', $booking);
    }

    /**
     * 格式化签到提醒通知内容
     */
    public function formatCheckinRemind($booking, ?int $tenantId = null)
    {
        return $this->templateService->render($tenantId, self::TYPE_RESERVATION_CHECKIN, $booking);
    }

    public function formatReservationCancel($booking, ?int $tenantId = null)
    {
        return $this->templateService->render($tenantId, self::TYPE_RESERVATION_CANCEL, $booking);
    }

    public function formatApprovalRemind($booking, ?int $tenantId = null)
    {
        return $this->templateService->render($tenantId, self::TYPE_APPROVAL_REMIND, $booking);
    }
}