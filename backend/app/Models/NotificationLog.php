<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = [
        'tenant_id', 'channel', 'user_id', 'type', 'content', 'status', 'error', 'callback_data'
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
