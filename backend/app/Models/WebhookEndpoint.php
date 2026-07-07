<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'name', 'url', 'secret', 'events', 'is_active',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    public const EVENTS = [
        'reservation.created',
        'reservation.updated',
        'reservation.cancelled',
        'reservation.checkin',
        'reservation.no_show',
    ];
}
