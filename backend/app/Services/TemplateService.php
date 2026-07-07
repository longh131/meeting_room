<?php

namespace App\Services;

use App\Models\MessageTemplate;
use App\Models\Tenant;

class TemplateService
{
    public const VARIABLES = [
        'title', 'room_name', 'start_time', 'end_time', 'user_name', 'reason', 'minutes',
    ];

    public function render(?int $tenantId, string $type, array $vars): string
    {
        if ($tenantId) {
            $this->ensureDefaults($tenantId);
            $template = MessageTemplate::where('tenant_id', $tenantId)
                ->where('type', $type)
                ->where('is_active', true)
                ->first();

            if ($template) {
                return $this->replaceVars($template->body, $vars);
            }
        }

        $defaults = $this->getDefaultBodies();
        $body = $defaults[$type] ?? "【系统通知】\n{title}";

        return $this->replaceVars($body, $vars);
    }

    public function replaceVars(string $body, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }

        return $body;
    }

    public function ensureDefaults(int $tenantId): void
    {
        if (!Tenant::find($tenantId)) {
            return;
        }

        if (MessageTemplate::where('tenant_id', $tenantId)->exists()) {
            return;
        }

        foreach ($this->getDefaultTemplates() as $tpl) {
            MessageTemplate::create(array_merge($tpl, ['tenant_id' => $tenantId]));
        }
    }

    public function getDefaultTemplates(): array
    {
        return [
            ['type' => NotificationService::TYPE_RESERVATION_CREATE, 'name' => '预定成功', 'body' => $this->getDefaultBodies()[NotificationService::TYPE_RESERVATION_CREATE], 'is_active' => true],
            ['type' => NotificationService::TYPE_RESERVATION_APPROVE, 'name' => '审批通过', 'body' => $this->getDefaultBodies()[NotificationService::TYPE_RESERVATION_APPROVE], 'is_active' => true],
            ['type' => NotificationService::TYPE_RESERVATION_REJECT, 'name' => '审批驳回', 'body' => $this->getDefaultBodies()[NotificationService::TYPE_RESERVATION_REJECT], 'is_active' => true],
            ['type' => NotificationService::TYPE_RESERVATION_CANCEL, 'name' => '预定取消/释放', 'body' => $this->getDefaultBodies()[NotificationService::TYPE_RESERVATION_CANCEL], 'is_active' => true],
            ['type' => NotificationService::TYPE_RESERVATION_CHECKIN, 'name' => '签到提醒', 'body' => $this->getDefaultBodies()[NotificationService::TYPE_RESERVATION_CHECKIN], 'is_active' => true],
            ['type' => NotificationService::TYPE_RESERVATION_EXTEND, 'name' => '延长会议', 'body' => $this->getDefaultBodies()[NotificationService::TYPE_RESERVATION_EXTEND], 'is_active' => true],
            ['type' => NotificationService::TYPE_APPROVAL_REMIND, 'name' => '审批催办', 'body' => $this->getDefaultBodies()[NotificationService::TYPE_APPROVAL_REMIND], 'is_active' => true],
            ['type' => 'meeting_remind', 'name' => '会前提醒', 'body' => $this->getDefaultBodies()['meeting_remind'], 'is_active' => true],
        ];
    }

    public function getDefaultBodies(): array
    {
        return [
            NotificationService::TYPE_RESERVATION_CREATE => "【会议室预定成功】\n会议主题: {title}\n会议室: {room_name}\n时间: {start_time} ~ {end_time}",
            NotificationService::TYPE_RESERVATION_APPROVE => "【预定审批通过】\n会议主题: {title}\n会议室: {room_name}\n时间: {start_time} ~ {end_time}",
            NotificationService::TYPE_RESERVATION_REJECT => "【预定审批驳回】\n会议主题: {title}\n原因: {reason}",
            NotificationService::TYPE_RESERVATION_CANCEL => "【会议室已释放】\n会议主题: {title}\n会议室: {room_name}\n原因: {reason}",
            NotificationService::TYPE_RESERVATION_CHECKIN => "【请签到】\n会议主题: {title}\n会议室: {room_name}\n请扫描会议室二维码完成签到",
            NotificationService::TYPE_RESERVATION_EXTEND => "【会议已延长】\n会议主题: {title}\n会议室: {room_name}\n新的结束时间: {end_time}",
            NotificationService::TYPE_APPROVAL_REMIND => "【审批催办】\n会议主题: {title}\n会议室: {room_name}\n时间: {start_time}\n请尽快处理",
            'meeting_remind' => "【会议即将开始】\n会议主题: {title}\n会议室: {room_name}\n时间: {start_time}\n还有 {minutes} 分钟开始",
        ];
    }

    public function getTypeLabels(): array
    {
        return [
            NotificationService::TYPE_RESERVATION_CREATE => '预定成功',
            NotificationService::TYPE_RESERVATION_APPROVE => '审批通过',
            NotificationService::TYPE_RESERVATION_REJECT => '审批驳回',
            NotificationService::TYPE_RESERVATION_CANCEL => '取消/释放',
            NotificationService::TYPE_RESERVATION_CHECKIN => '签到提醒',
            NotificationService::TYPE_RESERVATION_EXTEND => '延长会议',
            NotificationService::TYPE_APPROVAL_REMIND => '审批催办',
            'meeting_remind' => '会前提醒',
        ];
    }
}
