<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes, TenantAware;

    protected $fillable = ['tenant_id', 'name', 'parent_id', 'code', 'status', 'sort_order'];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function manager()
    {
        return $this->hasOne(User::class)->where('is_manager', 1);
    }
}
