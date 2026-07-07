<?php

namespace App\Console\Commands;

use App\Models\Approval;
use App\Models\Tenant;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RemindPendingApprovals extends Command
{
    protected $signature = 'approvals:remind-pending';

    protected $description = '审批超时自动催办';

    public function handle(NotificationService $notificationService)
    {
        $total = 0;

        Tenant::where('status', true)->each(function (Tenant $tenant) use ($notificationService, &$total) {
            $intervalHours = $tenant->approval_remind_hours ?? 2;
            $maxReminds = $tenant->approval_max_reminds ?? 3;
            $threshold = now()->subHours($intervalHours);

            $approvals = Approval::withoutGlobalScopes()
                ->with('reservation')
                ->where('tenant_id', $tenant->id)
                ->where('status', Approval::STATUS_PENDING)
                ->where('created_at', '<=', $threshold)
                ->where(function ($q) use ($threshold) {
                    $q->whereNull('last_reminded_at')
                      ->orWhere('last_reminded_at', '<=', $threshold);
                })
                ->where('remind_count', '<', $maxReminds)
                ->get();

            foreach ($approvals as $approval) {
                if (!$approval->reservation) {
                    continue;
                }

                $reservation = $approval->reservation;
                $reservation->load('meetingRoom');

                $notificationService->sendByUserSource(
                    $approval->approver_id,
                    NotificationService::TYPE_APPROVAL_REMIND,
                    $notificationService->formatApprovalRemind([
                        'title' => $reservation->title,
                        'room_name' => $reservation->meetingRoom->name ?? '',
                        'start_time' => $reservation->start_time->format('Y-m-d H:i'),
                    ], $tenant->id)
                );

                $approval->remind_count = ($approval->remind_count ?? 0) + 1;
                $approval->last_reminded_at = now();
                $approval->save();

                $total++;
                Log::info("[ApprovalRemind] 租户{$tenant->id} 审批#{$approval->id} 第{$approval->remind_count}次催办");
            }
        });

        $this->info("共催办 {$total} 条待审批");
        return 0;
    }
}
