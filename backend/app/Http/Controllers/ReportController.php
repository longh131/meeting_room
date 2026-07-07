<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Department;
use App\Models\MeetingRoom;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    public function roomUtilization(Request $request)
    {
        $this->authorize('admin');

        $startDate = $request->start_date ?? Carbon::now()->startOfMonth();
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth();
        $tenantId = $request->user()->tenant_id;
        $cacheKey = "report:utilization:{$tenantId}:{$startDate}:{$endDate}";

        $data = Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) {
            $rooms = MeetingRoom::where('status', 1)->get();
            $result = [];

            foreach ($rooms as $room) {
                $reservations = $room->reservations()
                    ->where('status', Reservation::STATUS_ENDED)
                    ->whereBetween('start_time', [$startDate, $endDate])
                    ->get();

                $totalMinutes = $reservations->sum(function ($r) {
                    return $r->end_time->diffInMinutes($r->start_time);
                });

                $result[] = [
                    'room' => $room,
                    'total_minutes' => $totalMinutes,
                    'utilization_rate' => $this->calculateUtilizationRate($totalMinutes, $startDate, $endDate),
                ];
            }

            return $result;
        });

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
        $this->authorize('admin');

        $startDate = $request->start_date ?? Carbon::now()->startOfMonth();
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth();
        $tenantId = $request->user()->tenant_id;
        $cacheKey = "report:dept_ranking:{$tenantId}:{$startDate}:{$endDate}";

        $data = Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) {
            $departments = Department::with('users')->get();
            $result = [];

            foreach ($departments as $dept) {
                $userIds = $dept->users->pluck('id')->toArray();

                $count = Reservation::whereIn('user_id', $userIds)
                    ->where('status', '!=', Reservation::STATUS_CANCELLED)
                    ->whereBetween('start_time', [$startDate, $endDate])
                    ->count();

                if ($count > 0) {
                    $result[] = [
                        'department' => $dept,
                        'count' => $count,
                    ];
                }
            }

            usort($result, fn ($a, $b) => $b['count'] - $a['count']);

            return $result;
        });

        return response()->json($data);
    }

    public function noShowRate(Request $request)
    {
        $this->authorize('admin');

        $startDate = $request->start_date ?? Carbon::now()->startOfMonth();
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth();
        $tenantId = $request->user()->tenant_id;
        $cacheKey = "report:no_show:{$tenantId}:{$startDate}:{$endDate}";

        $data = Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) {
            $totalReservations = Reservation::where('status', '!=', Reservation::STATUS_CANCELLED)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->count();

            $noShowCount = Reservation::where('status', Reservation::STATUS_NO_SHOW)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->count();

            $rate = $totalReservations > 0 ? round(($noShowCount / $totalReservations) * 100, 2) : 0;

            return [
                'total_reservations' => $totalReservations,
                'no_show_count' => $noShowCount,
                'no_show_rate' => $rate,
            ];
        });

        return response()->json($data);
    }

    public function overview(Request $request)
    {
        $this->authorize('admin');

        $startDate = $request->start_date ?? Carbon::now()->startOfMonth();
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth();
        $tenantId = $request->user()->tenant_id;
        $cacheKey = "report:overview:{$tenantId}:{$startDate}:{$endDate}";

        $stats = Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) {
            return [
                'total_rooms' => MeetingRoom::where('status', 1)->count(),
                'total_reservations' => Reservation::where('status', '!=', Reservation::STATUS_CANCELLED)
                    ->whereBetween('start_time', [$startDate, $endDate])->count(),
                'pending_approvals' => Approval::where('status', Approval::STATUS_PENDING)->count(),
                'ongoing_meetings' => Reservation::where('status', Reservation::STATUS_CHECKIN)->count(),
            ];
        });

        return response()->json($stats);
    }

    public function heatmap(Request $request)
    {
        $this->authorize('admin');

        $startDate = Carbon::parse($request->start_date ?? Carbon::now()->startOfMonth());
        $endDate = Carbon::parse($request->end_date ?? Carbon::now()->endOfMonth());
        $tenantId = $request->user()->tenant_id;
        $meetingRoomId = $request->meeting_room_id;
        $cacheKey = "report:heatmap:{$tenantId}:{$startDate->toDateString()}:{$endDate->toDateString()}:{$meetingRoomId}";

        $data = Cache::remember($cacheKey, 300, function () use ($startDate, $endDate, $meetingRoomId) {
            $matrix = [];
            for ($h = 8; $h <= 21; $h++) {
                for ($d = 1; $d <= 7; $d++) {
                    $matrix[$h][$d] = 0;
                }
            }

            $query = Reservation::whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_NO_SHOW])
                ->whereBetween('start_time', [$startDate->startOfDay(), $endDate->endOfDay()]);

            if ($meetingRoomId) {
                $query->where('meeting_room_id', $meetingRoomId);
            }

            $reservations = $query->get();

            foreach ($reservations as $r) {
                $hour = (int) $r->start_time->format('G');
                if ($hour < 8 || $hour > 21) {
                    continue;
                }
                $weekday = $r->start_time->dayOfWeekIso;
                $matrix[$hour][$weekday]++;
            }

            $rows = [];
            for ($h = 8; $h <= 21; $h++) {
                $row = ['hour' => $h, 'days' => []];
                for ($d = 1; $d <= 7; $d++) {
                    $row['days'][] = ['weekday' => $d, 'count' => $matrix[$h][$d]];
                }
                $rows[] = $row;
            }

            $maxCount = 0;
            foreach ($matrix as $hours) {
                foreach ($hours as $count) {
                    $maxCount = max($maxCount, $count);
                }
            }

            return [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'max_count' => $maxCount,
                'matrix' => $rows,
            ];
        });

        return response()->json($data);
    }

    public function exportUtilization(Request $request)
    {
        $this->authorize('admin');

        $startDate = $request->start_date ?? Carbon::now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? Carbon::now()->endOfMonth()->toDateString();

        $request->merge(['start_date' => $startDate, 'end_date' => $endDate]);
        $utilization = json_decode($this->roomUtilization($request)->getContent(), true);

        $lines = ["会议室,楼层,容量,使用分钟,利用率(%)"];
        foreach ($utilization as $item) {
            $room = $item['room'];
            $lines[] = sprintf(
                '%s,%sF,%s,%s,%s',
                $this->csvEscape($room['name']),
                $room['floor'],
                $room['capacity'],
                $item['total_minutes'],
                $item['utilization_rate']
            );
        }

        $csv = "\xEF\xBB\xBF" . implode("\n", $lines);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="room-utilization.csv"',
        ]);
    }

    protected function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"')) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
