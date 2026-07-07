<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'type', 'name', 'body', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
