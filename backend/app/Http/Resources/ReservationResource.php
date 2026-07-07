<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'meeting_room_id' => $this->meeting_room_id,
            'user_id' => $this->user_id,
            'booked_by_user_id' => $this->booked_by_user_id,
            'title' => $this->title,
            'description' => $this->description,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'need_approval' => (bool) $this->need_approval,
            'checkin_time' => $this->checkin_time,
            'qr_code' => $this->when($request->user()?->id === $this->user_id, $this->qr_code),
            'meeting_room' => new MeetingRoomResource($this->whenLoaded('meetingRoom')),
            'user' => new UserResource($this->whenLoaded('user')),
            'booked_by' => new UserResource($this->whenLoaded('bookedBy')),
            'approval' => $this->whenLoaded('approval'),
            'attendees' => UserResource::collection($this->whenLoaded('attendees')),
            'created_at' => $this->created_at,
        ];
    }
}
