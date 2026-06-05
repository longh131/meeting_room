<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\MeetingRoom;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function overview()
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 1)->count();
        $totalUsers = User::withoutGlobalScopes()->count();
        $totalRooms = MeetingRoom::withoutGlobalScopes()->count();
        $totalBookings = Reservation::withoutGlobalScopes()->count();

        $todayBookings = Reservation::withoutGlobalScopes()
            ->whereDate('created_at', today())
            ->count();

        return response()->json([
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'total_users' => $totalUsers,
            'total_rooms' => $totalRooms,
            'total_bookings' => $totalBookings,
            'today_bookings' => $todayBookings,
        ]);
    }

    public function tenantStats($id)
    {
        $tenant = Tenant::findOrFail($id);

        $stats = [
            'tenant' => $tenant,
            'users_count' => $tenant->users()->count(),
            'rooms_count' => $tenant->meetingRooms()->count(),
            'today_bookings' => $tenant->reservations()
                ->whereDate('start_time', today())
                ->count(),
            'week_bookings' => $tenant->reservations()
                ->whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'month_bookings' => $tenant->reservations()
                ->whereMonth('start_time', now()->month)
                ->count(),
        ];

        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = $tenant->reservations()
                ->whereYear('start_time', $month->year)
                ->whereMonth('start_time', $month->month)
                ->count();
            $monthlyTrend[] = [
                'month' => $month->format('Y-m'),
                'count' => $count,
            ];
        }
        $stats['monthly_trend'] = $monthlyTrend;

        return response()->json($stats);
    }

    public function allTenantsOverview()
    {
        $tenants = Tenant::withCount(['users', 'meetingRooms'])
            ->withSum('reservations as total_bookings', 'id')
            ->get()
            ->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'domain' => $tenant->domain,
                    'status' => $tenant->status,
                    'subscription_until' => $tenant->subscription_until,
                    'users_count' => $tenant->users_count,
                    'rooms_count' => $tenant->meeting_rooms_count,
                    'total_bookings' => $tenant->total_bookings ?? 0,
                    'is_active' => $tenant->isActive(),
                ];
            });

        return response()->json($tenants);
    }
}
