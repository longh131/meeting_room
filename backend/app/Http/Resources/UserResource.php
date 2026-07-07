<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department'),
            'position' => $this->position,
            'is_manager' => (bool) $this->is_manager,
            'is_admin' => (bool) $this->is_admin,
            'is_super_admin' => (bool) $this->is_super_admin,
            'credit_score' => $this->credit_score,
            'avatar' => $this->avatar,
            'status' => (bool) $this->status,
            'source' => $this->source,
            'created_at' => $this->created_at,
        ];
    }
}
