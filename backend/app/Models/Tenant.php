<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'domain', 'contact_name', 'contact_phone', 'contact_email',
        'status', 'subscription_until',
        // 钉钉配置
        'dingtalk_enabled', 'dingtalk_app_key', 'dingtalk_app_secret',
        'dingtalk_corp_id', 'dingtalk_agent_id', 'dingtalk_process_code',
        // 飞书配置
        'feishu_enabled', 'feishu_app_id', 'feishu_app_secret', 'feishu_verification_token', 'feishu_app_type', 'feishu_approval_code',
        // 企业微信配置
        'wework_enabled', 'wework_corp_id', 'wework_secret',
        'wework_agent_id', 'wework_token', 'wework_encoding_aes_key', 'wework_approval_code',
        // IM通知渠道
        'im_notification_channel',
    ];

    protected $casts = [
        'subscription_until' => 'date',
        'status' => 'boolean',
        'dingtalk_enabled' => 'boolean',
        'feishu_enabled' => 'boolean',
        'wework_enabled' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function meetingRooms()
    {
        return $this->hasMany(MeetingRoom::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function isActive()
    {
        if (!$this->status) {
            return false;
        }
        if ($this->subscription_until && $this->subscription_until->lt(now())) {
            return false;
        }
        return true;
    }

    public function getStats()
    {
        return [
            'users_count' => $this->users()->count(),
            'rooms_count' => $this->meetingRooms()->count(),
            'reservations_count' => $this->reservations()->count(),
            'active_reservations_count' => $this->reservations()->whereIn('status', [1, 2])->count(),
        ];
    }
}
