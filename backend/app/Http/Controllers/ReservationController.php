<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Approval;
use App\Models\Reservation;
use App\Models\ReservationAttendee;
use App\Models\User;
use App\Services\BookingPolicyService;
use App\Services\CreditService;
use App\Services\IM\IMServiceFactory;
use App\Services\NotificationService;
use App\Services\WaitlistService;
use App\Services\WebhookService;
use App\Jobs\SyncReservationCalendarJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReservationController extends Controller
{
    protected $notificationService;
    protected $bookingPolicy;

    public function __construct(NotificationService $notificationService, BookingPolicyService $bookingPolicy)
    {
        $this->notificationService = $notificationService;
        $this->bookingPolicy = $bookingPolicy;
    }

    public function calendar(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'meeting_room_id' => 'nullable|integer',
        ]);

        $query = Reservation::with(['meetingRoom', 'user', 'bookedBy'])
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED])
            ->where('start_time', '<', $request->end_date . ' 23:59:59')
            ->where('end_time', '>', $request->start_date . ' 00:00:00');

        if ($request->meeting_room_id) {
            $query->where('meeting_room_id', $request->meeting_room_id);
        }

        if ($request->boolean('my_only')) {
            $userId = $request->user()->id;
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereHas('attendees', fn ($aq) => $aq->where('users.id', $userId));
            });
        }

        return response()->json($query->orderBy('start_time')->get());
    }

    public function reschedule(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);
        $this->authorize('update', $reservation);

        $request->validate([
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $start = Carbon::parse($request->start_time);
        $end = Carbon::parse($request->end_time);

        $policyCheck = $this->bookingPolicy->validate($request->user(), $start, $end);
        if (!$policyCheck['valid']) {
            return response()->json(['error' => $policyCheck['message']], 400);
        }

        if (!$reservation->meetingRoom->isAvailable($request->start_time, $request->end_time, $id)) {
            return response()->json(['error' => '会议室在此时间段不可用'], 400);
        }

        $reservation->update([
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'remind_sent' => null,
        ]);

        return response()->json($reservation->load(['meetingRoom', 'user']));
    }

    public function index(Request $request)
    {
        $query = Reservation::with(['meetingRoom', 'user', 'approval']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('meeting_room_id')) {
            $query->where('meeting_room_id', $request->meeting_room_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date')) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('end_time', '<=', $request->end_date);
        }

        $reservations = $query->orderBy('start_time', 'desc')->get();

        return response()->json($reservations);
    }

    public function show($id)
    {
        $reservation = Reservation::with(['meetingRoom', 'user', 'bookedBy', 'attendees', 'approval'])->findOrFail($id);
        return new ReservationResource($reservation);
    }

    public function store(StoreReservationRequest $request)
    {
        $room = \App\Models\MeetingRoom::findOrFail($request->meeting_room_id);
        
        if (! $room->isAvailable($request->start_time, $request->end_time)) {
            return response()->json(['error' => '会议室在此时间段不可用'], 400);
        }

        foreach ($request->attendees ?? [] as $attendeeId) {
            if ($this->checkAttendeeConflict($attendeeId, $request->start_time, $request->end_time)) {
                $user = User::find($attendeeId);
                return response()->json(['error' => "参会人 {$user->name} 在此时间段有冲突"], 400);
            }
        }

        $user = $request->user();
        $start = Carbon::parse($request->start_time);
        $end = Carbon::parse($request->end_time);

        $targetUser = $user;
        if ($request->booked_for_user_id && (int) $request->booked_for_user_id !== $user->id) {
            $targetUser = User::findOrFail($request->booked_for_user_id);
            if (!$this->canBookFor($user, $targetUser)) {
                return response()->json(['error' => '无权为此用户预定'], 403);
            }
        }

        $policyCheck = $this->bookingPolicy->validate($targetUser, $start, $end);
        if (!$policyCheck['valid']) {
            return response()->json(['error' => $policyCheck['message']], 400);
        }

        $needApproval = !$targetUser->is_admin && !$targetUser->is_manager;

        $reservation = Reservation::create([
            'meeting_room_id' => $request->meeting_room_id,
            'user_id' => $targetUser->id,
            'booked_by_user_id' => $targetUser->id === $user->id ? null : $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'repeat_type' => $request->repeat_type,
            'repeat_end_date' => $request->repeat_end_date,
            'repeat_count' => $request->repeat_count,
            'need_approval' => $needApproval,
        ]);
        $reservation->status = $needApproval ? Reservation::STATUS_PENDING : Reservation::STATUS_RESERVED;
        $reservation->save();

        $reservation->generateQRCode();

        foreach ($request->attendees ?? [] as $attendeeId) {
            ReservationAttendee::create([
                'reservation_id' => $reservation->id,
                'user_id' => $attendeeId,
                'status' => 0,
            ]);
        }

        if ($needApproval) {
            $approver = $this->getApprover($targetUser);
            $approval = Approval::create([
                'reservation_id' => $reservation->id,
                'approver_id' => $approver->id,
                'status' => Approval::STATUS_PENDING,
            ]);

            $this->triggerIMApproval($reservation, $approval, $targetUser);

            $this->notificationService->send(
                $approver->id,
                NotificationService::TYPE_APPROVAL_REMIND,
                $this->notificationService->formatApprovalRemind([
                    'title' => $reservation->title,
                    'room_name' => $room->name,
                    'start_time' => $reservation->start_time->format('Y-m-d H:i'),
                ], $targetUser->tenant_id)
            );
        }

        if ($request->repeat_type) {
            $this->createRecurringReservations($reservation);
        }

        $this->afterReservationSaved($reservation, 'created');

        return (new ReservationResource($reservation))->response()->setStatusCode(201);
    }

    protected function afterReservationSaved(Reservation $reservation, string $event): void
    {
        $reservation->load(['meetingRoom', 'user']);

        app(WebhookService::class)->dispatch(
            $reservation->tenant_id,
            "reservation.{$event}",
            [
                'id' => $reservation->id,
                'title' => $reservation->title,
                'status' => $reservation->status,
                'start_time' => $reservation->start_time?->toIso8601String(),
                'end_time' => $reservation->end_time?->toIso8601String(),
                'meeting_room' => $reservation->meetingRoom?->only(['id', 'name']),
                'user' => $reservation->user?->only(['id', 'name', 'email']),
            ]
        );

        if (config('queue.default') !== 'sync') {
            SyncReservationCalendarJob::dispatch($reservation->id, 'upsert');
        } else {
            app(\App\Services\CalendarSyncService::class)->syncReservation($reservation);
        }
    }

    protected function afterReservationCancelled(Reservation $reservation): void
    {
        $reservation->load(['meetingRoom', 'user']);
        $room = $reservation->meetingRoom;

        app(WebhookService::class)->dispatch(
            $reservation->tenant_id,
            'reservation.cancelled',
            ['id' => $reservation->id, 'title' => $reservation->title]
        );

        if (config('queue.default') !== 'sync') {
            SyncReservationCalendarJob::dispatch($reservation->id, 'delete');
        }

        if ($room) {
            app(WaitlistService::class)->processRoomAvailable(
                $room,
                $reservation->start_time,
                $reservation->end_time
            );
        }
    }

    protected function checkAttendeeConflict($userId, $startTime, $endTime)
    {
        return \App\Models\ReservationAttendee::where('user_id', $userId)
            ->whereHas('reservation', function ($q) use ($startTime, $endTime) {
                $q->where('status', '!=', 5)
                  ->where('status', '!=', 4)
                  ->where(function ($q2) use ($startTime, $endTime) {
                      $q2->whereBetween('start_time', [$startTime, $endTime])
                         ->orWhereBetween('end_time', [$startTime, $endTime])
                         ->orWhere(function ($q3) use ($startTime, $endTime) {
                             $q3->where('start_time', '<=', $startTime)
                                ->where('end_time', '>=', $endTime);
                         });
                  });
            })->exists();
    }

    protected function getApprover($user)
    {
        if ($user->department && $user->department->manager) {
            return $user->department->manager;
        }

        return User::where('is_admin', 1)->first();
    }

    protected function canBookFor(User $booker, User $target): bool
    {
        if ($booker->tenant_id !== $target->tenant_id) {
            return false;
        }

        if ($booker->is_admin) {
            return true;
        }

        if ($booker->is_manager && $booker->department_id && $booker->department_id === $target->department_id) {
            return true;
        }

        return false;
    }

    /**
     * 向 IM 平台发起审批并保存实例 ID
     */
    protected function triggerIMApproval(Reservation $reservation, Approval $approval, User $user): void
    {
        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            return;
        }

        $imService = IMServiceFactory::getEnabledService($tenantId);
        if (!$imService || !$imService->isEnabled()) {
            return;
        }

        $reservation->load('meetingRoom');

        $booking = [
            'title' => $reservation->title,
            'room_name' => $reservation->meetingRoom->name ?? '',
            'start_time' => $reservation->start_time->format('Y-m-d H:i:s'),
            'end_time' => $reservation->end_time->format('Y-m-d H:i:s'),
            'user_name' => $user->name,
            'dingtalk_user_id' => $user->dingtalk_user_id,
            'feishu_open_id' => $user->feishu_open_id,
            'department_id' => $user->department_id,
        ];

        try {
            $result = $imService->sendApproval($booking);
            if (!empty($result['success']) && !empty($result['process_instance_id'])) {
                $approval->update(['process_instance_id' => $result['process_instance_id']]);
                Log::info("[Reservation] IM审批已发起: reservation={$reservation->id}, instance={$result['process_instance_id']}");
            }
        } catch (\Exception $e) {
            Log::warning("[Reservation] IM审批发起失败，回退为内部审批: {$e->getMessage()}");
        }
    }

    protected function createRecurringReservations($master)
    {
        $current = Carbon::parse($master->start_time);
        $endDate = $master->repeat_end_date ? Carbon::parse($master->repeat_end_date) : null;
        $count = $master->repeat_count ?? 100;
        $created = 0;

        while ($created < $count) {
            switch ($master->repeat_type) {
                case 'daily':
                    $current->addDay();
                    break;
                case 'weekly':
                    $current->addWeek();
                    break;
                case 'biweekly':
                    $current->addWeeks(2);
                    break;
                case 'monthly':
                    $current->addMonth();
                    break;
            }

            if ($endDate && $current->gt($endDate)) {
                break;
            }

            $endTime = $current->copy()->addMinutes($master->end_time->diffInMinutes($master->start_time));

            if (! $master->meetingRoom->isAvailable($current, $endTime)) {
                continue;
            }

            $child = Reservation::create([
                'meeting_room_id' => $master->meeting_room_id,
                'user_id' => $master->user_id,
                'title' => $master->title,
                'description' => $master->description,
                'start_time' => $current,
                'end_time' => $endTime,
                'parent_id' => $master->id,
                'need_approval' => 0,
            ]);
            $child->status = $master->status;
            $child->save();
            $child->generateQRCode();

            $created++;
        }
    }

    public function update(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);
        $this->authorize('update', $reservation);

        $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'attendees' => 'nullable|array',
        ]);

        if (! $reservation->meetingRoom->isAvailable($request->start_time, $request->end_time, $id)) {
            return response()->json(['error' => '会议室在此时间段不可用'], 400);
        }

        $start = Carbon::parse($request->start_time);
        $end = Carbon::parse($request->end_time);
        $policyCheck = $this->bookingPolicy->validate($request->user(), $start, $end);
        if (!$policyCheck['valid']) {
            return response()->json(['error' => $policyCheck['message']], 400);
        }

        $reservation->update([
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        $existingAttendees = $reservation->attendees->pluck('id')->toArray();
        $newAttendees = $request->attendees ?? [];

        foreach (array_diff($newAttendees, $existingAttendees) as $attendeeId) {
            ReservationAttendee::create([
                'reservation_id' => $reservation->id,
                'user_id' => $attendeeId,
                'status' => 0,
            ]);
        }

        foreach (array_diff($existingAttendees, $newAttendees) as $attendeeId) {
            ReservationAttendee::where('reservation_id', $reservation->id)
                ->where('user_id', $attendeeId)->delete();
        }

        return response()->json($reservation);
    }

    public function destroy($id)
    {
        $reservation = Reservation::findOrFail($id);
        $this->authorize('update', $reservation);

        $reservation->cancel();
        $this->afterReservationCancelled($reservation);

        return response()->json(['message' => '取消成功']);
    }

    public function checkin(Request $request, $id)
    {
        $request->validate([
            'qr_code' => 'required|string',
        ]);

        $reservation = Reservation::findOrFail($id);

        if ($reservation->qr_code !== $request->qr_code) {
            return response()->json(['error' => '二维码无效'], 400);
        }

        if (! $reservation->checkin()) {
            return response()->json(['error' => '无法签到'], 400);
        }

        $tenant = \App\Models\Tenant::find($reservation->tenant_id);
        app(CreditService::class)->rewardCheckin(
            User::find($reservation->user_id),
            $tenant,
            $reservation->id
        );

        app(WebhookService::class)->dispatch(
            $reservation->tenant_id,
            'reservation.checkin',
            ['id' => $reservation->id, 'title' => $reservation->title]
        );

        $this->notificationService->send(
            $reservation->user_id,
            NotificationService::TYPE_RESERVATION_CHECKIN,
            "会议已签到：{$reservation->title}"
        );

        return response()->json($reservation);
    }

    public function checkout($id)
    {
        $reservation = Reservation::findOrFail($id);
        $this->authorize('update', $reservation);

        if (! $reservation->checkout()) {
            return response()->json(['error' => '无法结束会议'], 400);
        }

        return response()->json($reservation);
    }

    public function extend(Request $request, $id)
    {
        $request->validate([
            'new_end_time' => 'required|date|after:now',
        ]);

        $reservation = Reservation::findOrFail($id);
        $this->authorize('update', $reservation);

        if (! $reservation->canExtend($request->new_end_time)) {
            return response()->json(['error' => '后续时段已被占用'], 400);
        }

        $reservation->end_time = $request->new_end_time;
        $reservation->is_extended = 1;
        $reservation->save();

        $this->notificationService->send(
            $reservation->user_id,
            NotificationService::TYPE_RESERVATION_EXTEND,
            "会议已延长至 {$request->new_end_time}"
        );

        return response()->json($reservation);
    }

    public function exportICS($id)
    {
        $reservation = Reservation::with(['meetingRoom', 'user'])->findOrFail($id);

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//MeetingRoom//EN\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . $reservation->id . "@meeting.sisuu.com\r\n";
        $ics .= "DTSTART:" . $reservation->start_time->format('Ymd\THis') . "\r\n";
        $ics .= "DTEND:" . $reservation->end_time->format('Ymd\THis') . "\r\n";
        $ics .= "SUMMARY:" . $reservation->title . "\r\n";
        $ics .= "DESCRIPTION:" . ($reservation->description ?? '') . "\r\n";
        $ics .= "LOCATION:" . $reservation->meetingRoom->name . "\r\n";
        $ics .= "ORGANIZER:" . $reservation->user->email . "\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return response($ics)
            ->header('Content-Type', 'text/calendar')
            ->header('Content-Disposition', 'attachment; filename="meeting_' . $reservation->id . '.ics"');
    }
}