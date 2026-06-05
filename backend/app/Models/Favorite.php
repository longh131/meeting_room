<?php

namespace App\Models;

use App\Models\Traits\TenantAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasFactory, TenantAware;

    protected $fillable = ['tenant_id', 'user_id', 'meeting_room_id'];

    public function meetingRoom()
    {
        return $this->belongsTo(MeetingRoom::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
