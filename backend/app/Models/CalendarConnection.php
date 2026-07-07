<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class CalendarConnection extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'user_id', 'provider', 'access_token', 'refresh_token',
        'expires_at', 'calendar_id', 'account_email', 'sync_enabled',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'sync_enabled' => 'boolean',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
