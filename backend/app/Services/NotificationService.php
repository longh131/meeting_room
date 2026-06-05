<?php

namespace App\Services;

use App\Models\NotificationLog;
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

    protected function sendLog($userId, $type, $content)
    {
        Log::info('[Notification] User: ' . $userId . ', Type: ' . $type . ', Content: ' . $content);
    }

    protected function sendDingtalk($userId, $type, $content)
    {
        Log::info('[Dingtalk Notification] User: ' . $userId . ', Type: ' . $type . ', Content: ' . $content);
    }

    protected function sendFeishu($userId, $type, $content)
    {
        Log::info('[Feishu Notification] User: ' . $userId . ', Type: ' . $type . ', Content: ' . $content);
    }

    protected function sendEmail($userId, $type, $content)
    {
        Log::info('[Email Notification] User: ' . $userId . ', Type: ' . $type . ', Content: ' . $content);
    }
}