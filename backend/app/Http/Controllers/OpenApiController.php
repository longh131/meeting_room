<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\MeetingRoom;
use App\Models\Reservation;
use App\Models\TenantApiToken;
use App\Services\BookingPolicyService;
use App\Services\CreditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OpenApiController extends Controller
{
    public function meetingRooms(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        $rooms = MeetingRoom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $rooms]);
    }

    public function reservations(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        $query = Reservation::withoutGlobalScopes()
            ->with(['meetingRoom', 'user'])
            ->where('tenant_id', $tenantId);

        if ($request->start_date) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('end_time', '<=', $request->end_date);
        }

        return response()->json(['data' => $query->orderByDesc('start_time')->paginate(50)]);
    }

    public function createReservation(Request $request, BookingPolicyService $policy, CreditService $credit)
    {
        $tenantId = $request->attributes->get('tenant_id');

        $request->validate([
            'user_id' => 'required|integer',
            'meeting_room_id' => 'required|integer',
            'title' => 'required|string|max:100',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        $user = \App\Models\User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($request->user_id);

        $room = MeetingRoom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($request->meeting_room_id);

        if (!$this->roomIsAvailable($room, $tenantId, $request->start_time, $request->end_time)) {
            return response()->json(['error' => '会议室不可用'], 400);
        }

        $start = Carbon::parse($request->start_time);
        $end = Carbon::parse($request->end_time);

        $creditCheck = $credit->canBook($user);
        if (!$creditCheck['valid']) {
            return response()->json(['error' => $creditCheck['message']], 400);
        }

        $policyCheck = $policy->validate($user, $start, $end);
        if (!$policyCheck['valid']) {
            return response()->json(['error' => $policyCheck['message']], 400);
        }

        $reservation = Reservation::create([
            'tenant_id' => $tenantId,
            'meeting_room_id' => $room->id,
            'user_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'need_approval' => !$user->is_admin && !$user->is_manager,
        ]);
        $reservation->status = $reservation->need_approval
            ? Reservation::STATUS_PENDING
            : Reservation::STATUS_RESERVED;
        $reservation->save();
        $reservation->generateQRCode();

        app(\App\Services\WebhookService::class)->dispatch(
            $tenantId,
            'reservation.created',
            $this->reservationPayload($reservation)
        );

        return (new ReservationResource($reservation))->response()->setStatusCode(201);
    }

    public function cancelReservation(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        $reservation = Reservation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $reservation->cancel();

        app(\App\Services\WebhookService::class)->dispatch(
            $tenantId,
            'reservation.cancelled',
            $this->reservationPayload($reservation)
        );

        return response()->json(['message' => '已取消']);
    }

    protected function roomIsAvailable(MeetingRoom $room, int $tenantId, string $start, string $end): bool
    {
        if (!$room->status) {
            return false;
        }

        return !Reservation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('meeting_room_id', $room->id)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_NO_SHOW])
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
    }

    protected function reservationPayload(Reservation $reservation): array
    {
        $reservation->load(['meetingRoom', 'user']);

        return [
            'id' => $reservation->id,
            'title' => $reservation->title,
            'status' => $reservation->status,
            'start_time' => $reservation->start_time?->toIso8601String(),
            'end_time' => $reservation->end_time?->toIso8601String(),
            'meeting_room' => $reservation->meetingRoom?->only(['id', 'name', 'code']),
            'user' => $reservation->user?->only(['id', 'name', 'email']),
        ];
    }
}
