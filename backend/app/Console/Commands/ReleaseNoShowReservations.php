<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreditService;
use App\Services\NotificationService;
use App\Services\WaitlistService;
use App\Services\WebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReleaseNoShowReservations extends Command
{
    protected $signature = 'reservations:release-no-show';

    protected $description = '释放未签到的会议室（Ghost Meeting 治理）';

    public function handle(NotificationService $notificationService)
    {
        $total = 0;

        Tenant::where('status', true)->each(function (Tenant $tenant) use ($notificationService, &$total) {
            $graceMinutes = $tenant->no_show_grace_minutes ?? 15;
            $deductCredit = $tenant->no_show_deduct_credit ?? 10;
            $deadline = now()->subMinutes($graceMinutes);

            $reservations = Reservation::withoutGlobalScopes()
                ->with('meetingRoom')
                ->where('tenant_id', $tenant->id)
                ->where('status', Reservation::STATUS_RESERVED)
                ->whereNull('checkin_time')
                ->where('start_time', '<=', $deadline)
                ->where('end_time', '>', now())
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->status = Reservation::STATUS_NO_SHOW;
                $reservation->save();

                if ($deductCredit > 0) {
                    $bookUser = User::find($reservation->user_id);
                    if ($bookUser) {
                        app(CreditService::class)->penalizeNoShow($bookUser, $tenant, $reservation->id);
                    }
                }

                $room = $reservation->meetingRoom;
                if ($room) {
                    app(WaitlistService::class)->processRoomAvailable(
                        $room,
                        $reservation->start_time,
                        $reservation->end_time
                    );
                }

                app(WebhookService::class)->dispatch(
                    $tenant->id,
                    'reservation.no_show',
                    [
                        'id' => $reservation->id,
                        'title' => $reservation->title,
                        'meeting_room_id' => $reservation->meeting_room_id,
                    ]
                );

                $reservation->load('meetingRoom');
                $notificationService->sendByUserSource(
                    $reservation->user_id,
                    NotificationService::TYPE_RESERVATION_CANCEL,
                    $notificationService->formatReservationCancel([
                        'title' => $reservation->title,
                        'room_name' => $reservation->meetingRoom->name ?? '',
                        'reason' => "未签到自动释放，信用分 -{$deductCredit}",
                    ], $tenant->id)
                );

                $total++;
                Log::info("[NoShow] 租户{$tenant->id} 释放预定 #{$reservation->id}");
            }

            if ($reservations->count() > 0) {
                $this->info("租户 [{$tenant->name}] 释放 {$reservations->count()} 个未签到会议");
            }
        });

        $this->info("共释放 {$total} 个未签到会议");
        return 0;
    }
}
