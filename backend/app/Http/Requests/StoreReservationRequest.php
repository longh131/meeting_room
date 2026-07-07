<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_room_id' => 'required|integer|exists:meeting_rooms,id',
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'attendees' => 'nullable|array',
            'attendees.*' => 'integer|exists:users,id',
            'booked_for_user_id' => 'nullable|integer|exists:users,id',
            'repeat_type' => 'nullable|in:daily,weekly,monthly,biweekly',
            'repeat_end_date' => 'nullable|date',
            'repeat_count' => 'nullable|integer|min:1',
        ];
    }
}
