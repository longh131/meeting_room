<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class RoomBlackout extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'meeting_room_id', 'start_time', 'end_time',
        'reason', 'repeat_type', 'created_by',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function meetingRoom()
    {
        return $this->belongsTo(MeetingRoom::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function overlaps($startTime, $endTime): bool
    {
        return $this->start_time < $endTime && $this->end_time > $startTime;
    }
}
