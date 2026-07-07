<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use App\Models\RoomBlackout;
use Illuminate\Http\Request;

class RoomBlackoutController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('admin');

        $query = RoomBlackout::with(['meetingRoom', 'creator'])
            ->orderBy('start_time', 'desc');

        if ($request->meeting_room_id) {
            $query->where('meeting_room_id', $request->meeting_room_id);
        }

        if ($request->start_date) {
            $query->where('end_time', '>=', $request->start_date . ' 00:00:00');
        }

        if ($request->end_date) {
            $query->where('start_time', '<=', $request->end_date . ' 23:59:59');
        }

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    public function store(Request $request)
    {
        $this->authorize('admin');

        $request->validate([
            'meeting_room_id' => 'required|integer|exists:meeting_rooms,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'reason' => 'nullable|string|max:200',
            'repeat_type' => 'nullable|in:none,weekly',
        ]);

        $room = MeetingRoom::findOrFail($request->meeting_room_id);

        if (!$room->isAvailableForBlackout($request->start_time, $request->end_time)) {
            return response()->json(['error' => '该时段与已有预定或维护时段冲突'], 400);
        }

        $blackout = RoomBlackout::create([
            'meeting_room_id' => $request->meeting_room_id,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'reason' => $request->reason,
            'repeat_type' => $request->repeat_type ?? 'none',
            'created_by' => $request->user()->id,
        ]);

        return response()->json($blackout->load(['meetingRoom', 'creator']), 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('admin');

        $blackout = RoomBlackout::findOrFail($id);

        $request->validate([
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'reason' => 'nullable|string|max:200',
            'repeat_type' => 'nullable|in:none,weekly',
        ]);

        $room = MeetingRoom::findOrFail($blackout->meeting_room_id);

        if (!$room->isAvailableForBlackout($request->start_time, $request->end_time, $blackout->id)) {
            return response()->json(['error' => '该时段与已有预定或维护时段冲突'], 400);
        }

        $blackout->update($request->only(['start_time', 'end_time', 'reason', 'repeat_type']));

        return response()->json($blackout->load(['meetingRoom', 'creator']));
    }

    public function destroy(Request $request, $id)
    {
        $this->authorize('admin');

        $blackout = RoomBlackout::findOrFail($id);
        $blackout->delete();

        return response()->json(['message' => '删除成功']);
    }
}
