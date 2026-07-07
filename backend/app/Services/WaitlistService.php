<?php

namespace App\Services;

use App\Models\MeetingRoom;
use App\Models\Reservation;
use App\Models\ReservationWaitlist;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

class WaitlistService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function join(User $user, int $roomId, Carbon $start, Carbon $end, ?string $title = null): ReservationWaitlist
    {
        $room = MeetingRoom::findOrFail($roomId);

        if ($room->isAvailable($start, $end)) {
            throw new \InvalidArgumentException('该时段会议室空闲，请直接预定');
        }

        $exists = ReservationWaitlist::where('user_id', $user->id)
            ->where('meeting_room_id', $roomId)
            ->where('start_time', $start)
            ->whereIn('status', [ReservationWaitlist::STATUS_QUEUED, ReservationWaitlist::STATUS_NOTIFIED])
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('您已在该时段候补队列中');
        }

        return ReservationWaitlist::create([
            'user_id' => $user->id,
            'meeting_room_id' => $roomId,
            'title' => $title,
            'start_time' => $start,
            'end_time' => $end,
            'status' => ReservationWaitlist::STATUS_QUEUED,
        ]);
    }

    public function processRoomAvailable(?MeetingRoom $room, Carbon $start, Carbon $end): void
    {
        if (!$room) {
            return;
        }

        $entry = ReservationWaitlist::where('meeting_room_id', $room->id)
            ->where('status', ReservationWaitlist::STATUS_QUEUED)
            ->where('start_time', '<=', $end)
            ->where('end_time', '>=', $start)
            ->orderBy('created_at')
            ->first();

        if (!$entry || !$room->isAvailable($entry->start_time, $entry->end_time)) {
            return;
        }

        $tenant = Tenant::find($entry->tenant_id);
        $confirmMinutes = $tenant?->waitlist_confirm_minutes ?? 30;

        $entry->update([
            'status' => ReservationWaitlist::STATUS_NOTIFIED,
            'notified_at' => now(),
            'expires_at' => now()->addMinutes($confirmMinutes),
        ]);

        $this->notificationService->sendByUserSource(
            $entry->user_id,
            NotificationService::TYPE_RESERVATION_CREATE,
            "【候补通知】{$room->name} 在 {$entry->start_time->format('m-d H:i')} 有空位，请在 {$confirmMinutes} 分钟内确认预定"
        );
    }

    public function confirm(User $user, int $waitlistId): Reservation
    {
        $entry = ReservationWaitlist::where('user_id', $user->id)->findOrFail($waitlistId);

        if ($entry->status !== ReservationWaitlist::STATUS_NOTIFIED) {
            throw new \InvalidArgumentException('当前候补状态不可确认');
        }

        if ($entry->expires_at && $entry->expires_at->isPast()) {
            $entry->update(['status' => ReservationWaitlist::STATUS_EXPIRED]);
            throw new \InvalidArgumentException('确认时限已过期');
        }

        $room = MeetingRoom::findOrFail($entry->meeting_room_id);
        if (!$room->isAvailable($entry->start_time, $entry->end_time)) {
            throw new \InvalidArgumentException('会议室已被占用');
        }

        $reservation = Reservation::create([
            'meeting_room_id' => $entry->meeting_room_id,
            'user_id' => $user->id,
            'title' => $entry->title ?: '候补预定会议',
            'start_time' => $entry->start_time,
            'end_time' => $entry->end_time,
            'need_approval' => !$user->is_admin && !$user->is_manager,
        ]);
        $reservation->status = $reservation->need_approval
            ? Reservation::STATUS_PENDING
            : Reservation::STATUS_RESERVED;
        $reservation->save();
        $reservation->generateQRCode();

        $entry->update(['status' => ReservationWaitlist::STATUS_CONFIRMED]);

        return $reservation;
    }

    public function expireStale(): int
    {
        $count = 0;
        ReservationWaitlist::where('status', ReservationWaitlist::STATUS_NOTIFIED)
            ->where('expires_at', '<', now())
            ->each(function (ReservationWaitlist $entry) use (&$count) {
                $entry->update(['status' => ReservationWaitlist::STATUS_EXPIRED]);
                $count++;
                $this->processRoomAvailable(
                    MeetingRoom::find($entry->meeting_room_id),
                    $entry->start_time,
                    $entry->end_time
                );
            });

        return $count;
    }
}
