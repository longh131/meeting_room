<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, SoftDeletes, TenantAware;

    const STATUS_PENDING = 0;
    const STATUS_RESERVED = 1;
    const STATUS_CHECKIN = 2;
    const STATUS_ENDED = 3;
    const STATUS_NO_SHOW = 4;
    const STATUS_CANCELLED = 5;

    protected $fillable = [
        'tenant_id', 'meeting_room_id', 'user_id', 'booked_by_user_id',
        'google_event_id', 'outlook_event_id', 'title', 'description', 'start_time', 'end_time',
        'repeat_type', 'repeat_end_date', 'repeat_count', 'parent_id',
        'need_approval', 'qr_code', 'checkin_time', 'actual_attendees', 'is_extended', 'remind_sent'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'repeat_end_date' => 'date',
        'checkin_time' => 'datetime',
        'remind_sent' => 'array',
    ];

    public function meetingRoom()
    {
        return $this->belongsTo(MeetingRoom::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }

    public function attendees()
    {
        return $this->belongsToMany(User::class, 'reservation_attendees')
            ->withPivot('tenant_id', 'status')
            ->withTimestamps();
    }

    public function approval()
    {
        return $this->hasOne(Approval::class);
    }

    public function children()
    {
        return $this->hasMany(Reservation::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Reservation::class, 'parent_id');
    }

    public function getStatusTextAttribute()
    {
        $statusMap = [
            self::STATUS_PENDING => '待审批',
            self::STATUS_RESERVED => '已预定',
            self::STATUS_CHECKIN => '签到进行中',
            self::STATUS_ENDED => '已结束',
            self::STATUS_NO_SHOW => '已爽约',
            self::STATUS_CANCELLED => '已取消',
        ];
        return $statusMap[$this->status] ?? '未知';
    }

    public function canExtend($newEndTime)
    {
        return $this->meetingRoom->isAvailable($this->end_time, $newEndTime, $this->id);
    }

    public function generateQRCode()
    {
        $this->qr_code = base64_encode(uniqid('qr_', true));
        $this->save();
        return $this->qr_code;
    }

    public function checkin()
    {
        if ($this->status == self::STATUS_RESERVED && now()->between($this->start_time, $this->end_time)) {
            $this->status = self::STATUS_CHECKIN;
            $this->checkin_time = now();
            $this->save();
            return true;
        }
        return false;
    }

    public function checkout()
    {
        if ($this->status == self::STATUS_CHECKIN) {
            $this->status = self::STATUS_ENDED;
            $this->save();
            return true;
        }
        return false;
    }

    public function cancel()
    {
        if ($this->status != self::STATUS_ENDED && $this->status != self::STATUS_NO_SHOW && $this->status != self::STATUS_CANCELLED) {
            $this->status = self::STATUS_CANCELLED;
            $this->save();
            return true;
        }
        return false;
    }
}
