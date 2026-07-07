<?php

namespace App\Services;

use App\Models\CreditLog;
use App\Models\Tenant;
use App\Models\User;

class CreditService
{
    public function adjust(User $user, int $amount, string $reason, ?int $reservationId = null): User
    {
        $newScore = max(0, min(200, ($user->credit_score ?? 100) + $amount));
        $user->credit_score = $newScore;
        $user->save();

        CreditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'change_amount' => $amount,
            'balance_after' => $newScore,
            'reason' => $reason,
            'reservation_id' => $reservationId,
        ]);

        return $user->fresh();
    }

    public function canBook(User $user, ?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? Tenant::find($user->tenant_id);
        if (!$tenant || $user->is_admin || $user->is_manager) {
            return ['valid' => true, 'message' => ''];
        }

        $threshold = $tenant->credit_min_threshold ?? 60;
        $score = $user->credit_score ?? 100;

        if ($score < $threshold) {
            return [
                'valid' => false,
                'message' => "信用分 {$score} 低于最低要求 {$threshold}，暂不可预定",
            ];
        }

        return ['valid' => true, 'message' => ''];
    }

    public function rewardCheckin(User $user, ?Tenant $tenant, ?int $reservationId): void
    {
        $bonus = $tenant?->credit_checkin_bonus ?? 1;
        if ($bonus > 0) {
            $this->adjust($user, $bonus, '准时签到奖励', $reservationId);
        }
    }

    public function penalizeNoShow(User $user, ?Tenant $tenant, ?int $reservationId): void
    {
        $penalty = $tenant?->no_show_deduct_credit ?? 10;
        if ($penalty > 0) {
            $this->adjust($user, -$penalty, '未签到爽约扣分', $reservationId);
        }
    }
}
