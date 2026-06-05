<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeetingRoom extends Model
{
    use HasFactory, SoftDeletes, TenantAware;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'floor', 'capacity', 'description', 'photo', 'floor_plan',
        'device_tags', 'hourly_rate', 'status', 'sort_order'
    ];

    protected $casts = [
        'device_tags' => 'array',
        'status' => 'boolean',
    ];

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withPivot('tenant_id');
    }

    public function isAvailable($startTime, $endTime, $excludeReservationId = null)
    {
        $query = $this->reservations()
            ->where('status', '!=', 5)
            ->where('status', '!=', 4)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                  ->orWhereBetween('end_time', [$startTime, $endTime])
                  ->orWhere(function ($q2) use ($startTime, $endTime) {
                      $q2->where('start_time', '<=', $startTime)
                         ->where('end_time', '>=', $endTime);
                  });
            });

        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        return $query->count() === 0;
    }
}
