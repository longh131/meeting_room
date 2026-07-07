<?php

namespace App\Services\IM;

use App\Models\MeetingRoom;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * IM 机器人指令处理（钉钉/飞书）
 *
 * 支持指令：
 * - 帮助
 * - 查空闲 [楼层] [HH:MM-HH:MM]
 * - 今日会议 / 我的会议
 */
class IMBotService
{
    public function handle(int $tenantId, string $text, ?string $imUserId = null, string $platform = 'dingtalk'): array
    {
        $text = trim($text);
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return $this->reply('租户不存在');
        }

        if (in_array($text, ['帮助', 'help', '?', '？'])) {
            return $this->reply($this->helpText());
        }

        if (preg_match('/^(查空闲|空闲|查询空闲)/u', $text)) {
            return $this->handleAvailability($tenant, $text);
        }

        if (in_array($text, ['今日会议', '我的会议', '今天会议', '我的今日会议'])) {
            return $this->handleMyMeetings($tenantId, $imUserId, $platform);
        }

        return $this->reply("未识别指令，发送「帮助」查看可用命令。");
    }

    protected function handleAvailability(Tenant $tenant, string $text): array
    {
        $floor = null;
        if (preg_match('/(\d+)\s*楼/u', $text, $m)) {
            $floor = $m[1];
        }

        $start = now()->addHour()->startOfHour();
        $end = $start->copy()->addHour();

        if (preg_match('/(\d{1,2})[:：](\d{2})\s*[-~至到]\s*(\d{1,2})[:：](\d{2})/u', $text, $m)) {
            $start = now()->setTime((int)$m[1], (int)$m[2], 0);
            $end = now()->setTime((int)$m[3], (int)$m[4], 0);
            if ($end->lte($start)) {
                $end->addDay();
            }
        }

        $query = MeetingRoom::where('tenant_id', $tenant->id)->where('status', 1);
        if ($floor) {
            $query->where('floor', $floor);
        }
        $rooms = $query->get();

        $available = [];
        foreach ($rooms as $room) {
            if ($room->isAvailable($start, $end)) {
                $available[] = $room->name . "（{$room->floor}楼，{$room->capacity}人）";
            }
        }

        if (empty($available)) {
            $range = $start->format('H:i') . '-' . $end->format('H:i');
            return $this->reply("{$range} 暂无空闲会议室" . ($floor ? "（{$floor}楼）" : ''));
        }

        $range = $start->format('H:i') . '-' . $end->format('H:i');
        $list = implode("\n", array_slice($available, 0, 10));
        $more = count($available) > 10 ? "\n...等共 " . count($available) . " 间" : '';

        return $this->reply("【{$range} 空闲会议室】\n{$list}{$more}\n\n请登录系统预定：http://meeting.sisuu.com");
    }

    protected function handleMyMeetings(int $tenantId, ?string $imUserId, string $platform): array
    {
        if (!$imUserId) {
            return $this->reply('无法识别您的身份，请通过 IM 应用内打开。');
        }

        $field = $platform === 'feishu' ? 'feishu_open_id' : 'dingtalk_user_id';
        $user = User::where('tenant_id', $tenantId)->where($field, $imUserId)->first();

        if (!$user) {
            return $this->reply('未找到绑定的系统账号，请联系管理员同步组织架构。');
        }

        $reservations = Reservation::with('meetingRoom')
            ->where('user_id', $user->id)
            ->whereDate('start_time', today())
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_NO_SHOW])
            ->orderBy('start_time')
            ->get();

        if ($reservations->isEmpty()) {
            return $this->reply('今日暂无会议安排。');
        }

        $lines = $reservations->map(function ($r) {
            $room = $r->meetingRoom->name ?? '未知';
            $time = $r->start_time->format('H:i') . '-' . $r->end_time->format('H:i');
            return "• {$time} {$r->title} @{$room}";
        })->implode("\n");

        return $this->reply("【今日会议 - {$user->name}】\n{$lines}");
    }

    protected function helpText(): string
    {
        return "【会议室助手】可用指令：\n"
            . "1. 查空闲 — 如：查空闲 3楼 14:00-15:00\n"
            . "2. 今日会议 — 查看我今天安排的会议\n"
            . "3. 帮助 — 显示本说明";
    }

    protected function reply(string $message): array
    {
        return ['success' => true, 'message' => $message];
    }
}
