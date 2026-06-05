<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceTag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'icon', 'status', 'sort_order'];

    protected $casts = [
        'status' => 'boolean',
    ];
}