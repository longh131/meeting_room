<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use Illuminate\Http\Request;

class MeetingRoomController extends Controller
{
    public function index(Request $request)
    {
        $query = MeetingRoom::where('status', 1);

        if ($request->has('floor')) {
            $query->where('floor', $request->floor);
        }

        if ($request->has('capacity')) {
            $query->where('capacity', '>=', $request->capacity);
        }

        if ($request->has('keyword')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->keyword . '%')
                  ->orWhere('code', 'like', '%' . $request->keyword . '%');
            });
        }

        $rooms = $query->withCount('reservations')->orderBy('sort_order')->paginate($request->per_page ?? 10);

        $rooms->getCollection()->transform(function ($room) {
            $tagIds = $room->device_tags ?? [];
            if (is_array($tagIds) && !empty($tagIds)) {
                $tags = \App\Models\DeviceTag::whereIn('id', $tagIds)->get(['id', 'name']);
                $room->device_tags = $tags->toArray();
            } else {
                $room->device_tags = [];
            }
            return $room;
        });

        return response()->json([
            'data' => $rooms->items(),
            'total' => $rooms->total(),
        ]);
    }

    public function show($id)
    {
        $room = MeetingRoom::with(['reservations' => function ($q) {
            $q->where('status', '!=', 5)->where('end_time', '>', now());
        }])->findOrFail($id);

        return response()->json($room);
    }

    public function store(Request $request)
    {
        $this->authorize('admin');

        $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:30|unique:meeting_rooms',
            'floor' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'device_tags' => 'nullable|array',
        ]);

        $room = MeetingRoom::create([
            'name' => $request->name,
            'code' => $request->code,
            'floor' => $request->floor,
            'capacity' => $request->capacity,
            'description' => $request->description,
            'device_tags' => $request->device_tags ?? [],
            'photo' => $request->photo,
            'floor_plan' => $request->floor_plan,
            'hourly_rate' => $request->hourly_rate,
            'status' => 1,
        ]);

        return response()->json($room, 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('admin');

        $room = MeetingRoom::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:30|unique:meeting_rooms,code,' . $id,
            'floor' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'device_tags' => 'nullable|array',
        ]);

        $room->update([
            'name' => $request->name,
            'code' => $request->code,
            'floor' => $request->floor,
            'capacity' => $request->capacity,
            'description' => $request->description,
            'device_tags' => $request->device_tags ?? [],
            'photo' => $request->photo,
            'floor_plan' => $request->floor_plan,
            'hourly_rate' => $request->hourly_rate,
            'status' => $request->status,
        ]);

        return response()->json($room);
    }

    public function destroy($id)
    {
        $this->authorize('admin');

        $room = MeetingRoom::findOrFail($id);
        $room->delete();

        return response()->json(['message' => '删除成功']);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'meeting_room_id' => 'required|integer',
            'start_time' => 'required|datetime',
            'end_time' => 'required|datetime|after:start_time',
            'exclude_reservation_id' => 'nullable|integer',
        ]);

        $room = MeetingRoom::findOrFail($request->meeting_room_id);
        $available = $room->isAvailable($request->start_time, $request->end_time, $request->exclude_reservation_id);

        return response()->json(['available' => $available]);
    }

    public function getFloors()
    {
        $floors = MeetingRoom::where('status', 1)->distinct()->pluck('floor')->sort();
        return response()->json($floors);
    }

    /**
     * 获取会议室PAD显示信息（当前状态、二维码等）
     */
    public function padDisplay($id)
    {
        $room = MeetingRoom::findOrFail($id);
        
        // 获取当前和近期的预定
        $now = now();
        $currentReservation = $room->reservations()
            ->whereNotIn('status', [4, 5]) // 排除已取消和爽约的
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now)
            ->with('user', 'attendees')
            ->first();
            
        $nextReservation = $room->reservations()
            ->whereNotIn('status', [4, 5])
            ->where('start_time', '>', $now)
            ->orderBy('start_time')
            ->with('user', 'attendees')
            ->first();
            
        // 生成二维码内容（签到地址）
        $qrCodeContent = url("/api/pad/checkin/{$room->code}");
        
        // 计算会议室状态：0=空闲，1=使用中，2=即将使用（30分钟内）
        $status = 0;
        $statusText = '空闲';
        if ($currentReservation) {
            $status = 1;
            $statusText = '使用中';
        } elseif ($nextReservation && $nextReservation->start_time->diffInMinutes($now, false) <= 30) {
            $status = 2;
            $statusText = '即将使用';
        }
        
        return response()->json([
            'room' => $room,
            'status' => $status,
            'status_text' => $statusText,
            'current_time' => $now->format('Y-m-d H:i:s'),
            'current_reservation' => $currentReservation,
            'next_reservation' => $nextReservation,
            'qr_code_content' => $qrCodeContent,
            'today_reservations' => $room->reservations()
                ->whereNotIn('status', [4, 5])
                ->whereDate('start_time', $now->toDateString())
                ->with('user')
                ->orderBy('start_time')
                ->get(),
        ]);
    }

    /**
     * 通过会议室CODE签到
     */
    public function padCheckin($roomCode)
    {
        $room = MeetingRoom::where('code', $roomCode)->firstOrFail();
        
        $now = now();
        $currentReservation = $room->reservations()
            ->whereNotIn('status', [4, 5])
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now)
            ->first();
            
        if (!$currentReservation) {
            return response()->json(['message' => '当前无进行中的会议'], 400);
        }
        
        if ($currentReservation->checkin_time) {
            return response()->json(['message' => '会议已签到'], 400);
        }
        
        // 签到
        $currentReservation->update([
            'checkin_time' => $now,
            'status' => 3, // 签到进行中
        ]);
        
        return response()->json([
            'message' => '签到成功',
            'reservation' => $currentReservation,
        ]);
    }
}