<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceTag extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'icon', 'status', 'sort_order'];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSystemTag(): bool
    {
        return $this->tenant_id === null;
    }
}
