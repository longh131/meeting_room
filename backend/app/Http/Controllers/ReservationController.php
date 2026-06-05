<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Reservation;
use App\Models\ReservationAttendee;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReservationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
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
        $reservation = Reservation::with(['meetingRoom', 'user', 'attendees', 'approval'])->findOrFail($id);
        return response()->json($reservation);
    }

    public function store(Request $request)
    {
        $request->validate([
            'meeting_room_id' => 'required|integer',
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'attendees' => 'nullable|array',
            'repeat_type' => 'nullable|in:daily,weekly,monthly,biweekly',
            'repeat_end_date' => 'nullable|date',
            'repeat_count' => 'nullable|integer|min:1',
        ]);

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
        $needApproval = !$user->is_admin && !$user->is_manager;

        $reservation = Reservation::create([
            'meeting_room_id' => $request->meeting_room_id,
            'user_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => $needApproval ? Reservation::STATUS_PENDING : Reservation::STATUS_RESERVED,
            'repeat_type' => $request->repeat_type,
            'repeat_end_date' => $request->repeat_end_date,
            'repeat_count' => $request->repeat_count,
            'need_approval' => $needApproval,
        ]);

        $reservation->generateQRCode();

        foreach ($request->attendees ?? [] as $attendeeId) {
            ReservationAttendee::create([
                'reservation_id' => $reservation->id,
                'user_id' => $attendeeId,
                'status' => 0,
            ]);
        }

        if ($needApproval) {
            $approver = $this->getApprover($user);
            Approval::create([
                'reservation_id' => $reservation->id,
                'approver_id' => $approver->id,
                'status' => Approval::STATUS_PENDING,
            ]);

            $this->notificationService->send(
                $approver->id,
                NotificationService::TYPE_APPROVAL_REMIND,
                "有新的会议室预定需要审批：{$reservation->title}"
            );
        }

        if ($request->repeat_type) {
            $this->createRecurringReservations($reservation);
        }

        return response()->json($reservation, 201);
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

            Reservation::create([
                'meeting_room_id' => $master->meeting_room_id,
                'user_id' => $master->user_id,
                'title' => $master->title,
                'description' => $master->description,
                'start_time' => $current,
                'end_time' => $endTime,
                'status' => $master->status,
                'parent_id' => $master->id,
                'need_approval' => 0,
            ])->generateQRCode();

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