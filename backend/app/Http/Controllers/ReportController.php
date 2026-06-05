<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\MeetingRoom;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function roomUtilization(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth();
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth();

        $rooms = MeetingRoom::where('status', 1)->get();
        $data = [];

        foreach ($rooms as $room) {
            $reservations = $room->reservations()
                ->where('status', Reservation::STATUS_ENDED)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->get();

            $totalMinutes = $reservations->sum(function ($r) {
                return $r->end_time->diffInMinutes($r->start_time);
            });

            $data[] = [
                'room' => $room,
                'total_minutes' => $totalMinutes,
                'utilization_rate' => $this->calculateUtilizationRate($totalMinutes, $startDate, $endDate),
            ];
        }

        return response()->json($data);
    }

    protected function calculateUtilizationRate($totalMinutes, $startDate, $endDate)
    {
        $days = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $workingHoursPerDay = 8;
        $totalWorkingMinutes = $days * $workingHoursPerDay * 60;
        
        return $totalWorkingMinutes > 0 ? round(($totalMinutes / $totalWorkingMinutes) * 100, 2) : 0;
    }

    public function departmentRanking(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth();
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth();

        $departments = Department::with('users')->get();
        $data = [];

        foreach ($departments as $dept) {
            $userIds = $dept->users->pluck('id')->toArray();
            
            $count = Reservation::whereIn('user_id', $userIds)
                ->where('status', '!=', Reservation::STATUS_CANCELLED)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->count();

            if ($count > 0) {
                $data[] = [
                    'department' => $dept,
                    'count' => $count,
                ];
            }
        }

        usort($data, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        return response()->json($data);
    }

    public function noShowRate(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth();
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth();

        $totalReservations = Reservation::where('status', '!=', Reservation::STATUS_CANCELLED)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->count();

        $noShowCount = Reservation::where('status', Reservation::STATUS_NO_SHOW)
            ->whereBetween('start_time', [$startDate, $endDate])
            ->count();

        $rate = $totalReservations > 0 ? round(($noShowCount / $totalReservations) * 100, 2) : 0;

        return response()->json([
            'total_reservations' => $totalReservations,
            'no_show_count' => $noShowCount,
            'no_show_rate' => $rate,
        ]);
    }

    public function overview(Request $request)
    {
        $startDate = $request->start_date ?? Carbon::now()->startOfMonth();
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth();

        $stats = [
            'total_rooms' => MeetingRoom::where('status', 1)->count(),
            'total_reservations' => Reservation::where('status', '!=', Reservation::STATUS_CANCELLED)
                ->whereBetween('start_time', [$startDate, $endDate])->count(),
            'pending_approvals' => \App\Models\Approval::where('status', \App\Models\Approval::STATUS_PENDING)->count(),
            'ongoing_meetings' => Reservation::where('status', Reservation::STATUS_CHECKIN)->count(),
        ];

        return response()->json($stats);
    }
}