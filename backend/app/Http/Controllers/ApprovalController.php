<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Reservation;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $this->authorize('manager');

        $query = Approval::with(['reservation.meetingRoom', 'reservation.user', 'approver']);

        if ($request->has('approver_id')) {
            $query->where('approver_id', $request->approver_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $approvals = $query->orderBy('created_at', 'desc')->get();

        return response()->json($approvals);
    }

    public function show($id)
    {
        $this->authorize('manager');

        $approval = Approval::with(['reservation.meetingRoom', 'reservation.user', 'reservation.attendees'])->findOrFail($id);
        return response()->json($approval);
    }

    public function approve(Request $request, $id)
    {
        $this->authorize('manager');

        $request->validate([
            'comment' => 'nullable|string',
        ]);

        $approval = Approval::findOrFail($id);

        if ($approval->approver_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['error' => '无权限审批'], 403);
        }

        $approval->approve($request->comment);

        $reservation = $approval->reservation;
        $reservation->load('meetingRoom');
        $this->notificationService->sendByUserSource(
            $reservation->user_id,
            NotificationService::TYPE_RESERVATION_APPROVE,
            $this->notificationService->formatReservationApprove([
                'title' => $reservation->title,
                'room_name' => $reservation->meetingRoom->name ?? '',
                'start_time' => $reservation->start_time->format('Y-m-d H:i'),
                'end_time' => $reservation->end_time->format('Y-m-d H:i'),
            ], $reservation->tenant_id)
        );

        return response()->json($approval);
    }

    public function reject(Request $request, $id)
    {
        $this->authorize('manager');

        $request->validate([
            'comment' => 'required|string',
        ]);

        $approval = Approval::findOrFail($id);

        if ($approval->approver_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['error' => '无权限审批'], 403);
        }

        $approval->reject($request->comment);

        $reservation = $approval->reservation;
        $this->notificationService->sendByUserSource(
            $reservation->user_id,
            NotificationService::TYPE_RESERVATION_REJECT,
            $this->notificationService->formatReservationReject([
                'title' => $reservation->title,
            ], $request->comment, $reservation->tenant_id)
        );

        return response()->json($approval);
    }

    public function remind($id)
    {
        $this->authorize('manager');

        $approval = Approval::findOrFail($id);

        if ($approval->status !== Approval::STATUS_PENDING) {
            return response()->json(['error' => '审批状态不允许催办'], 400);
        }

        $reservation = $approval->reservation;
        $reservation->load('meetingRoom');

        $this->notificationService->sendByUserSource(
            $approval->approver_id,
            NotificationService::TYPE_APPROVAL_REMIND,
            $this->notificationService->formatApprovalRemind([
                'title' => $reservation->title,
                'room_name' => $reservation->meetingRoom->name ?? '',
                'start_time' => $reservation->start_time->format('Y-m-d H:i'),
            ], $reservation->tenant_id)
        );

        return response()->json(['message' => '催办通知已发送']);
    }
}
