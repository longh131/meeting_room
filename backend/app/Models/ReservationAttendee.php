<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationAttendee extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id', 'reservation_id', 'user_id', 'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
