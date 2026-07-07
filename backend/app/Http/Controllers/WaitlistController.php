<?php

namespace App\Http\Controllers;

use App\Models\ReservationWaitlist;
use App\Services\WaitlistService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function index(Request $request)
    {
        $items = ReservationWaitlist::with(['meetingRoom'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($items);
    }

    public function store(Request $request, WaitlistService $waitlistService)
    {
        $request->validate([
            'meeting_room_id' => 'required|integer|exists:meeting_rooms,id',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'title' => 'nullable|string|max:100',
        ]);

        try {
            $entry = $waitlistService->join(
                $request->user(),
                $request->meeting_room_id,
                Carbon::parse($request->start_time),
                Carbon::parse($request->end_time),
                $request->title
            );

            return response()->json($entry->load('meetingRoom'), 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function confirm(Request $request, $id, WaitlistService $waitlistService)
    {
        try {
            $reservation = $waitlistService->confirm($request->user(), (int) $id);
            return response()->json($reservation->load('meetingRoom'), 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy(Request $request, $id)
    {
        $entry = ReservationWaitlist::where('user_id', $request->user()->id)->findOrFail($id);
        $entry->update(['status' => ReservationWaitlist::STATUS_CANCELLED]);

        return response()->json(['message' => '已取消候补']);
    }
}
