<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class ReservationWaitlist extends Model
{
    use TenantAware;

    protected $table = 'reservation_waitlist';

    const STATUS_QUEUED = 0;
    const STATUS_NOTIFIED = 1;
    const STATUS_CONFIRMED = 2;
    const STATUS_EXPIRED = 3;
    const STATUS_CANCELLED = 4;

    protected $fillable = [
        'tenant_id', 'user_id', 'meeting_room_id', 'title',
        'start_time', 'end_time', 'status', 'notified_at', 'expires_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'notified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function meetingRoom()
    {
        return $this->belongsTo(MeetingRoom::class);
    }
}
