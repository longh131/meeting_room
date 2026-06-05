<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use HasFactory, TenantAware;

    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    protected $fillable = [
        'tenant_id', 'reservation_id', 'approver_id', 'status', 'comment', 'approved_at', 'callback_url'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function getStatusTextAttribute()
    {
        $statusMap = [
            self::STATUS_PENDING => '待审批',
            self::STATUS_APPROVED => '已通过',
            self::STATUS_REJECTED => '已驳回',
        ];
        return $statusMap[$this->status] ?? '未知';
    }

    public function approve($comment = '')
    {
        $this->status = self::STATUS_APPROVED;
        $this->comment = $comment;
        $this->approved_at = now();
        $this->save();
        
        $this->reservation->status = Reservation::STATUS_RESERVED;
        $this->reservation->save();
        
        return true;
    }

    public function reject($comment = '')
    {
        $this->status = self::STATUS_REJECTED;
        $this->comment = $comment;
        $this->approved_at = now();
        $this->save();
        
        $this->reservation->status = Reservation::STATUS_CANCELLED;
        $this->reservation->save();
        
        return true;
    }
}
