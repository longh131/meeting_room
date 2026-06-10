<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Services\IM\IMServiceFactory;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 审批回调控制器
 * 支持多租户架构，处理钉钉/飞书审批结果回调
 */
class ApprovalCallbackController extends Controller
{
    /**
     * 钉钉审批回调
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dingtalkCallback(Request $request)
    {
        try {
            $data = $request->all();
            $tenantId = $request->input('tenant_id');

            Log::info('[Dingtalk Callback] 租户' . $tenantId . '收到审批回调: ' . json_encode($data));

            if (!$tenantId) {
                Log::error('[Dingtalk Callback] 缺少租户ID');
                return response()->json(['errcode' => -1, 'errmsg' => '缺少租户ID'], 400);
            }

            $tenant = Tenant::find($tenantId);
            if (!$tenant || !$tenant->dingtalk_enabled) {
                Log::error('[Dingtalk Callback] 租户不存在或未启用钉钉: ' . $tenantId);
                return response()->json(['errcode' => -1, 'errmsg' => '租户未启用钉钉'], 400);
            }

            $imService = IMServiceFactory::createDingtalkService($tenantId);
            $result = $imService->handleApprovalCallback($data);

            if ($result['success']) {
                $this->updateReservationStatus($result['process_instance_id'], $result['status'], $tenantId);
            }

            return response()->json(['errcode' => 0, 'errmsg' => 'success']);
        } catch (\Exception $e) {
            Log::error('[Dingtalk Callback] 处理失败: ' . $e->getMessage());
            return response()->json(['errcode' => -1, 'errmsg' => '处理失败'], 500);
        }
    }

    /**
     * 飞书审批回调
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function feishuCallback(Request $request)
    {
        try {
            $data = $request->all();
            $tenantId = $request->input('tenant_id');

            Log::info('[Feishu Callback] 租户' . $tenantId . '收到审批回调: ' . json_encode($data));

            if (!$tenantId) {
                Log::error('[Feishu Callback] 缺少租户ID');
                return response()->json(['code' => -1, 'msg' => '缺少租户ID'], 400);
            }

            $tenant = Tenant::find($tenantId);
            if (!$tenant || !$tenant->feishu_enabled) {
                Log::error('[Feishu Callback] 租户不存在或未启用飞书: ' . $tenantId);
                return response()->json(['code' => -1, 'msg' => '租户未启用飞书'], 400);
            }

            $imService = IMServiceFactory::createFeishuService($tenantId);
            $result = $imService->handleApprovalCallback($data);

            if ($result['success']) {
                $this->updateReservationStatus($result['process_instance_id'], $result['status'], $tenantId);
            }

            return response()->json(['code' => 0, 'msg' => 'success']);
        } catch (\Exception $e) {
            Log::error('[Feishu Callback] 处理失败: ' . $e->getMessage());
            return response()->json(['code' => -1, 'msg' => '处理失败'], 500);
        }
    }

    /**
     * 更新预定状态
     *
     * @param string $processInstanceId
     * @param string $status
     * @param int $tenantId
     */
    protected function updateReservationStatus(string $processInstanceId, string $status, int $tenantId)
    {
        $approval = Approval::where('process_instance_id', $processInstanceId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$approval) {
            Log::warning('[Approval Callback] 未找到对应的审批记录: ' . $processInstanceId);
            return;
        }

        $reservation = Reservation::find($approval->reservation_id);

        if (!$reservation) {
            Log::warning('[Approval Callback] 未找到对应的预定记录: ' . $approval->reservation_id);
            return;
        }

        $notificationService = new NotificationService();

        switch ($status) {
            case 'approved':
                $reservation->status = 'approved';
                $approval->status = 'approved';
                $this->sendApprovalNotification($reservation, $notificationService, true);
                break;

            case 'rejected':
                $reservation->status = 'rejected';
                $approval->status = 'rejected';
                $this->sendApprovalNotification($reservation, $notificationService, false);
                break;

            default:
                Log::warning('[Approval Callback] 未知状态: ' . $status);
                return;
        }

        $reservation->save();
        $approval->save();

        Log::info('[Approval Callback] 租户' . $tenantId . '预定状态已更新: ' . $reservation->id . ' - ' . $status);
    }

    /**
     * 发送审批结果通知
     *
     * @param Reservation $reservation
     * @param NotificationService $notificationService
     * @param bool $approved
     */
    protected function sendApprovalNotification(Reservation $reservation, NotificationService $notificationService, bool $approved)
    {
        try {
            $booking = [
                'title' => $reservation->title,
                'room_name' => $reservation->meetingRoom->name ?? '',
                'start_time' => $reservation->start_time,
                'end_time' => $reservation->end_time,
            ];

            if ($approved) {
                $content = $notificationService->formatReservationApprove($booking);
                $notificationService->sendByUserSource($reservation->user_id, NotificationService::TYPE_RESERVATION_APPROVE, $content);
            } else {
                $content = $notificationService->formatReservationReject($booking);
                $notificationService->sendByUserSource($reservation->user_id, NotificationService::TYPE_RESERVATION_REJECT, $content);
            }
        } catch (\Exception $e) {
            Log::error('[Approval Callback] 发送通知失败: ' . $e->getMessage());
        }
    }

    /**
     * 审批校验接口（用于钉钉/飞书验证回调URL）
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        try {
            $signature = $request->input('signature');
            $timestamp = $request->input('timestamp');
            $nonce = $request->input('nonce');
            $echostr = $request->input('echostr');

            if (!empty($echostr)) {
                return response($echostr);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('[Approval Callback] 验证失败: ' . $e->getMessage());
            return response()->json(['success' => false], 403);
        }
    }
}