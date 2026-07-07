<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'code' => $this->code,
            'floor' => $this->floor,
            'capacity' => $this->capacity,
            'location' => $this->location,
            'device_tags' => $this->device_tags,
            'status' => (bool) $this->status,
            'access_code' => $this->when($request->user()?->is_admin, $this->access_code),
            'created_at' => $this->created_at,
        ];
    }
}
