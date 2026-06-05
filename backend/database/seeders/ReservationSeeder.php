<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\Reservation;
use App\Models\ReservationAttendee;
use App\Models\MeetingRoom;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReservationSeeder extends Seeder
{
    public function run()
    {
        $tenants = [1, 2];

        foreach ($tenants as $tenantId) {
            $this->createReservationsForTenant($tenantId);
        }

        $this->command->info('Created reservations for all tenants');
    }

    private function createReservationsForTenant($tenantId)
    {
        $rooms = MeetingRoom::where('tenant_id', $tenantId)->get();
        $users = User::where('tenant_id', $tenantId)->where('is_admin', 0)->get();

        if ($rooms->isEmpty() || $users->isEmpty()) {
            $this->command->warn("Skipping reservations for tenant {$tenantId} - no rooms or users found");
            return;
        }

        $room1 = $rooms->first();
        $room3 = $rooms->count() >= 3 ? $rooms[2] : $room1;
        $user2 = $users->count() >= 2 ? $users[1] : $users->first();
        $user3 = $users->count() >= 3 ? $users[2] : $users->first();

        $admin = User::where('tenant_id', $tenantId)->where('is_admin', 1)->first();

        $reservation1 = Reservation::create([
            'tenant_id' => $tenantId,
            'meeting_room_id' => $room1->id,
            'user_id' => $user2->id,
            'title' => '技术周会',
            'description' => '每周技术团队例会',
            'start_time' => Carbon::today()->addDay()->setTime(10, 0),
            'end_time' => Carbon::today()->addDay()->setTime(11, 0),
            'status' => 1,
            'repeat_type' => 'weekly',
            'repeat_end_date' => Carbon::today()->addMonth(),
            'need_approval' => 0,
            'qr_code' => base64_encode(uniqid('qr_', true)),
        ]);

        if ($user3) {
            ReservationAttendee::create([
                'tenant_id' => $tenantId,
                'reservation_id' => $reservation1->id,
                'user_id' => $user3->id,
                'status' => 1,
            ]);
        }

        $reservation2 = Reservation::create([
            'tenant_id' => $tenantId,
            'meeting_room_id' => $room3->id,
            'user_id' => $user3 ? $user3->id : $user2->id,
            'title' => '项目需求评审',
            'description' => '讨论新功能需求',
            'start_time' => Carbon::today()->addDays(2)->setTime(14, 0),
            'end_time' => Carbon::today()->addDays(2)->setTime(16, 0),
            'status' => 0,
            'need_approval' => 1,
            'qr_code' => base64_encode(uniqid('qr_', true)),
        ]);

        if ($admin) {
            Approval::create([
                'tenant_id' => $tenantId,
                'reservation_id' => $reservation2->id,
                'approver_id' => $admin->id,
                'status' => 0,
            ]);
        }

        if ($users->count() >= 4) {
            Reservation::create([
                'tenant_id' => $tenantId,
                'meeting_room_id' => $rooms->count() >= 2 ? $rooms[1]->id : $room1->id,
                'user_id' => $users[3]->id,
                'title' => '产品规划会议',
                'description' => 'Q2产品路线规划',
                'start_time' => Carbon::today()->addDays(3)->setTime(9, 0),
                'end_time' => Carbon::today()->addDays(3)->setTime(10, 30),
                'status' => 1,
                'need_approval' => 0,
                'qr_code' => base64_encode(uniqid('qr_', true)),
            ]);
        }

        if ($users->count() >= 5) {
            Reservation::create([
                'tenant_id' => $tenantId,
                'meeting_room_id' => $rooms->count() >= 4 ? $rooms[3]->id : $room1->id,
                'user_id' => $users[4]->id,
                'title' => '市场发布会筹备',
                'description' => '新品发布会准备工作',
                'start_time' => Carbon::today()->addDays(4)->setTime(14, 0),
                'end_time' => Carbon::today()->addDays(4)->setTime(17, 0),
                'status' => 1,
                'need_approval' => 0,
                'qr_code' => base64_encode(uniqid('qr_', true)),
            ]);
        }

        Reservation::create([
            'tenant_id' => $tenantId,
            'meeting_room_id' => $room1->id,
            'user_id' => $user2->id,
            'title' => '代码审查会议',
            'description' => '审查新提交的代码',
            'start_time' => Carbon::yesterday()->setTime(15, 0),
            'end_time' => Carbon::yesterday()->setTime(16, 0),
            'status' => 3,
            'need_approval' => 0,
            'qr_code' => base64_encode(uniqid('qr_', true)),
            'checkin_time' => Carbon::yesterday()->setTime(15, 5),
            'actual_attendees' => 2,
        ]);
    }
}
