<?php

namespace Database\Seeders;

use App\Models\MeetingRoom;
use Illuminate\Database\Seeder;

class MeetingRoomSeeder extends Seeder
{
    public function run()
    {
        MeetingRoom::create([
            'name' => '创新会议室',
            'code' => 'RM-001',
            'floor' => 1,
            'capacity' => 8,
            'description' => '适合小型团队会议，配备投影仪和白板',
            'device_tags' => json_encode([1, 2, 5]),
            'hourly_rate' => 100,
            'status' => 1,
            'sort_order' => 1,
        ]);

        MeetingRoom::create([
            'name' => '阳光会议室',
            'code' => 'RM-002',
            'floor' => 1,
            'capacity' => 4,
            'description' => '温馨舒适的小型会议室',
            'device_tags' => json_encode([2, 5]),
            'hourly_rate' => 60,
            'status' => 1,
            'sort_order' => 2,
        ]);

        MeetingRoom::create([
            'name' => '远景会议室',
            'code' => 'RM-003',
            'floor' => 2,
            'capacity' => 12,
            'description' => '中型会议室，适合部门会议',
            'device_tags' => json_encode([1, 2, 3, 5, 6]),
            'hourly_rate' => 150,
            'status' => 1,
            'sort_order' => 3,
        ]);

        MeetingRoom::create([
            'name' => '星辰会议室',
            'code' => 'RM-004',
            'floor' => 2,
            'capacity' => 20,
            'description' => '大型会议室，配备视频会议系统',
            'device_tags' => json_encode([1, 2, 3, 4, 5, 6, 7, 8]),
            'hourly_rate' => 200,
            'status' => 1,
            'sort_order' => 4,
        ]);

        MeetingRoom::create([
            'name' => '静思会议室',
            'code' => 'RM-005',
            'floor' => 3,
            'capacity' => 6,
            'description' => '安静私密的小型会议室',
            'device_tags' => json_encode([2, 5, 6]),
            'hourly_rate' => 80,
            'status' => 1,
            'sort_order' => 5,
        ]);

        MeetingRoom::create([
            'name' => '云端会议室',
            'code' => 'RM-006',
            'floor' => 3,
            'capacity' => 30,
            'description' => '公司最大的会议室，适合全体会议',
            'device_tags' => json_encode([1, 2, 3, 4, 5, 6, 7, 8]),
            'hourly_rate' => 300,
            'status' => 1,
            'sort_order' => 6,
        ]);
    }
}