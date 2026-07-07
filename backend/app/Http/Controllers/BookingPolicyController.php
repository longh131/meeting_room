<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\BookingPolicyService;
use Illuminate\Http\Request;

class BookingPolicyController extends Controller
{
    public function show(Request $request, BookingPolicyService $policyService)
    {
        $user = $request->user();
        if (!$user->tenant_id) {
            return response()->json(['message' => '无租户信息'], 400);
        }

        $tenant = Tenant::findOrFail($user->tenant_id);
        return response()->json($policyService->getPolicy($tenant));
    }

    public function update(Request $request, BookingPolicyService $policyService)
    {
        $this->authorize('admin');

        $user = $request->user();
        $tenant = Tenant::findOrFail($user->tenant_id);

        $request->validate([
            'max_duration_minutes' => 'nullable|integer|min:15|max:1440',
            'max_advance_days' => 'nullable|integer|min:1|max:365',
            'min_advance_minutes' => 'nullable|integer|min:0|max:1440',
            'max_daily_bookings_per_user' => 'nullable|integer|min:0|max:100',
            'booking_start_hour' => 'nullable|integer|min:0|max:23',
            'booking_end_hour' => 'nullable|integer|min:1|max:24',
            'no_show_grace_minutes' => 'nullable|integer|min:5|max:60',
            'no_show_deduct_credit' => 'nullable|integer|min:0|max:100',
            'meeting_remind_minutes' => 'nullable|string|max:50',
            'approval_remind_hours' => 'nullable|integer|min:1|max:72',
            'approval_max_reminds' => 'nullable|integer|min:0|max:10',
            'credit_min_threshold' => 'nullable|integer|min:0|max:100',
            'credit_checkin_bonus' => 'nullable|integer|min:0|max:20',
            'waitlist_confirm_minutes' => 'nullable|integer|min:5|max:120',
        ]);

        $tenant->update($request->only([
            'max_duration_minutes', 'max_advance_days', 'min_advance_minutes',
            'max_daily_bookings_per_user', 'booking_start_hour', 'booking_end_hour',
            'no_show_grace_minutes', 'no_show_deduct_credit', 'meeting_remind_minutes',
            'approval_remind_hours', 'approval_max_reminds',
            'credit_min_threshold', 'credit_checkin_bonus', 'waitlist_confirm_minutes',
        ]));

        return response()->json([
            'message' => '预定规则已更新',
            'data' => $policyService->getPolicy($tenant->fresh()),
        ]);
    }
}
