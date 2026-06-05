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
        $approval = Approval::with(['reservation.meetingRoom', 'reservation.user', 'reservation.attendees'])->findOrFail($id);
        return response()->json($approval);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'comment' => 'nullable|string',
        ]);

        $approval = Approval::findOrFail($id);
        
        if ($approval->approver_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['error' => '无权限审批'], 403);
        }

        $approval->approve($request->comment);

        $this->notificationService->send(
            $approval->reservation->user_id,
            NotificationService::TYPE_RESERVATION_APPROVE,
            "您的会议预定已通过审批：{$approval->reservation->title}"
        );

        return response()->json($approval);
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'comment' => 'required|string',
        ]);

        $approval = Approval::findOrFail($id);

        if ($approval->approver_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['error' => '无权限审批'], 403);
        }

        $approval->reject($request->comment);

        $this->notificationService->send(
            $approval->reservation->user_id,
            NotificationService::TYPE_RESERVATION_REJECT,
            "您的会议预定已被驳回：{$approval->reservation->title}，原因：{$request->comment}"
        );

        return response()->json($approval);
    }

    public function remind($id)
    {
        $approval = Approval::findOrFail($id);

        if ($approval->status !== Approval::STATUS_PENDING) {
            return response()->json(['error' => '审批状态不允许催办'], 400);
        }

        $this->notificationService->send(
            $approval->approver_id,
            NotificationService::TYPE_APPROVAL_REMIND,
            "请及时处理会议预定审批：{$approval->reservation->title}"
        );

        return response()->json(['message' => '催办通知已发送']);
    }
}