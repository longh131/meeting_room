<?php

namespace Database\Seeders;

use App\Models\DeviceTag;
use Illuminate\Database\Seeder;

class DeviceTagSeeder extends Seeder
{
    public function run()
    {
        DeviceTag::create(['name' => '投影仪', 'icon' => 'projector', 'status' => 1, 'sort_order' => 1]);
        DeviceTag::create(['name' => '白板', 'icon' => 'whiteboard', 'status' => 1, 'sort_order' => 2]);
        DeviceTag::create(['name' => '视频会议', 'icon' => 'video', 'status' => 1, 'sort_order' => 3]);
        DeviceTag::create(['name' => '电话会议', 'icon' => 'phone', 'status' => 1, 'sort_order' => 4]);
        DeviceTag::create(['name' => 'WiFi', 'icon' => 'wifi', 'status' => 1, 'sort_order' => 5]);
        DeviceTag::create(['name' => '空调', 'icon' => 'aircon', 'status' => 1, 'sort_order' => 6]);
        DeviceTag::create(['name' => '电视', 'icon' => 'tv', 'status' => 1, 'sort_order' => 7]);
        DeviceTag::create(['name' => '音响', 'icon' => 'speaker', 'status' => 1, 'sort_order' => 8]);
    }
}