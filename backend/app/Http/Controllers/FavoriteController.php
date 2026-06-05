<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\MeetingRoom;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = $request->user()->favorites()->with('meetingRoom')->get();
        return response()->json($favorites->map(function ($favorite) {
            return $favorite->meetingRoom;
        }));
    }

    public function store(Request $request)
    {
        $request->validate([
            'meeting_room_id' => 'required|integer|exists:meeting_rooms,id',
        ]);

        $favorite = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'meeting_room_id' => $request->meeting_room_id,
        ]);

        return response()->json($favorite, 201);
    }

    public function destroy(Request $request, $meetingRoomId)
    {
        Favorite::where('user_id', $request->user()->id)
            ->where('meeting_room_id', $meetingRoomId)->delete();

        return response()->json(['message' => '取消收藏成功']);
    }
}