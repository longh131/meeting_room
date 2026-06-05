<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, TenantAware;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'phone', 'department_id', 'position',
        'dingtalk_user_id', 'feishu_open_id', 'wework_user_id',
        'is_manager', 'is_admin', 'is_super_admin', 'credit_score', 'avatar', 'status'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_manager' => 'boolean',
        'is_admin' => 'boolean',
        'is_super_admin' => 'boolean',
        'status' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function attendedReservations()
    {
        return $this->belongsToMany(Reservation::class, 'reservation_attendees')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class, 'approver_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function isSuperAdmin()
    {
        return $this->is_super_admin;
    }

    public function isTenantAdmin()
    {
        return $this->is_admin && !$this->is_super_admin;
    }

    public function canAccessTenant($tenantId)
    {
        if ($this->is_super_admin) {
            return true;
        }
        return $this->tenant_id == $tenantId;
    }
}
