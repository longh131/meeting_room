<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;

trait TenantAware
{
    public static function bootTenantAware()
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            $user = auth()->user();
            if ($user && !$user->is_super_admin && is_null($model->tenant_id)) {
                $model->tenant_id = $user->tenant_id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeWithoutTenant($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId);
    }
}
