<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Model;

class TenantApiToken extends Model
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'name', 'token_prefix', 'token_hash', 'abilities', 'last_used_at', 'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
