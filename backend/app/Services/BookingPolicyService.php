<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

class BookingPolicyService
{
    public function __construct(protected CreditService $creditService) {}

    /**
     * 校验预定是否符合租户规则
     *
     * @return array{valid: bool, message: string}
     */
    public function validate(User $user, Carbon $startTime, Carbon $endTime, ?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? Tenant::find($user->tenant_id);
        if (!$tenant) {
            return ['valid' => true, 'message' => ''];
        }

        $creditCheck = $this->creditService->canBook($user, $tenant);
        if (!$creditCheck['valid']) {
            return $creditCheck;
        }

        $now = now();
        $durationMinutes = $startTime->diffInMinutes($endTime);

        if ($durationMinutes <= 0) {
            return ['valid' => false, 'message' => '结束时间必须晚于开始时间'];
        }

        if ($tenant->max_duration_minutes > 0 && $durationMinutes > $tenant->max_duration_minutes) {
            return ['valid' => false, 'message' => "单次预定最长 {$tenant->max_duration_minutes} 分钟"];
        }

        $minAdvance = $tenant->min_advance_minutes ?? 15;
        if ($startTime->lt($now->copy()->addMinutes($minAdvance))) {
            return ['valid' => false, 'message' => "需至少提前 {$minAdvance} 分钟预定"];
        }

        $maxAdvance = $tenant->max_advance_days ?? 30;
        if ($startTime->gt($now->copy()->addDays($maxAdvance))) {
            return ['valid' => false, 'message' => "最多只能提前 {$maxAdvance} 天预定"];
        }

        $startHour = (int) ($tenant->booking_start_hour ?? 8);
        $endHour = (int) ($tenant->booking_end_hour ?? 22);
        if ($startTime->hour < $startHour || $endTime->hour > $endHour ||
            ($endTime->hour == $endHour && $endTime->minute > 0)) {
            return ['valid' => false, 'message' => "可预定时段为 {$startHour}:00 - {$endHour}:00"];
        }

        if (!$user->is_admin && !$user->is_manager) {
            $dailyLimit = $tenant->max_daily_bookings_per_user ?? 0;
            if ($dailyLimit > 0) {
                $todayCount = Reservation::where('user_id', $user->id)
                    ->whereDate('start_time', $startTime->toDateString())
                    ->whereNotIn('status', [Reservation::STATUS_CANCELLED])
                    ->count();

                if ($todayCount >= $dailyLimit) {
                    return ['valid' => false, 'message' => "每人每日最多预定 {$dailyLimit} 次"];
                }
            }
        }

        return ['valid' => true, 'message' => ''];
    }

    public function getPolicy(Tenant $tenant): array
    {
        return [
            'max_duration_minutes' => $tenant->max_duration_minutes ?? 240,
            'max_advance_days' => $tenant->max_advance_days ?? 30,
            'min_advance_minutes' => $tenant->min_advance_minutes ?? 15,
            'max_daily_bookings_per_user' => $tenant->max_daily_bookings_per_user ?? 5,
            'booking_start_hour' => $tenant->booking_start_hour ?? 8,
            'booking_end_hour' => $tenant->booking_end_hour ?? 22,
            'no_show_grace_minutes' => $tenant->no_show_grace_minutes ?? 15,
            'no_show_deduct_credit' => $tenant->no_show_deduct_credit ?? 10,
            'meeting_remind_minutes' => $tenant->meeting_remind_minutes ?? '15,5',
            'approval_remind_hours' => $tenant->approval_remind_hours ?? 2,
            'approval_max_reminds' => $tenant->approval_max_reminds ?? 3,
            'credit_min_threshold' => $tenant->credit_min_threshold ?? 60,
            'credit_checkin_bonus' => $tenant->credit_checkin_bonus ?? 1,
            'waitlist_confirm_minutes' => $tenant->waitlist_confirm_minutes ?? 30,
        ];
    }
}
