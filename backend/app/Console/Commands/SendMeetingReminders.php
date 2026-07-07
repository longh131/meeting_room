<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\Tenant;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMeetingReminders extends Command
{
    protected $signature = 'reservations:send-reminders';

    protected $description = '发送会前提醒通知';

    public function handle(NotificationService $notificationService)
    {
        $total = 0;

        Tenant::where('status', true)->each(function (Tenant $tenant) use ($notificationService, &$total) {
            $minutesList = array_filter(array_map('intval', explode(',', $tenant->meeting_remind_minutes ?? '15,5')));

            foreach ($minutesList as $minutes) {
                if ($minutes <= 0) continue;

                $windowStart = now()->addMinutes($minutes)->subMinute();
                $windowEnd = now()->addMinutes($minutes)->addMinute();

                $reservations = Reservation::withoutGlobalScopes()
                    ->with('meetingRoom')
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('status', [Reservation::STATUS_RESERVED, Reservation::STATUS_PENDING])
                    ->whereBetween('start_time', [$windowStart, $windowEnd])
                    ->get();

                foreach ($reservations as $reservation) {
                    $sent = $reservation->remind_sent ?? [];
                    $key = (string) $minutes;

                    if (!empty($sent[$key])) {
                        continue;
                    }

                    $roomName = $reservation->meetingRoom->name ?? '';
                    $content = $notificationService->formatMeetingRemind([
                        'title' => $reservation->title,
                        'room_name' => $roomName,
                        'start_time' => $reservation->start_time->format('Y-m-d H:i'),
                        'minutes' => (string) $minutes,
                    ], $tenant->id);

                    $notificationService->sendByUserSource(
                        $reservation->user_id,
                        NotificationService::TYPE_RESERVATION_CREATE,
                        $content
                    );

                    $sent[$key] = true;
                    $reservation->remind_sent = $sent;
                    $reservation->save();

                    $total++;
                    Log::info("[Reminder] 租户{$tenant->id} 预定#{$reservation->id} {$minutes}分钟提醒");
                }
            }
        });

        $this->info("共发送 {$total} 条会前提醒");
        return 0;
    }
}
