<?php

namespace Tests\Feature;

use App\Models\MeetingRoom;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    public function test_admin_can_create_reservation_without_approval(): void
    {
        $admin = User::where('email', 'admin@demo.com')->first();
        $room = MeetingRoom::where('tenant_id', $admin->tenant_id)->first();

        $start = Carbon::now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addHour();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/reservations', [
                'meeting_room_id' => $room->id,
                'title' => '测试会议',
                'start_time' => $start->toDateTimeString(),
                'end_time' => $end->toDateTimeString(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', '测试会议');
    }

    public function test_reservation_conflict_is_rejected(): void
    {
        $admin = User::where('email', 'admin@demo.com')->first();
        $room = MeetingRoom::where('tenant_id', $admin->tenant_id)->first();

        $start = Carbon::now()->addDays(2)->setHour(14)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addHour();

        $payload = [
            'meeting_room_id' => $room->id,
            'title' => '冲突测试A',
            'start_time' => $start->toDateTimeString(),
            'end_time' => $end->toDateTimeString(),
        ];

        $this->actingAs($admin, 'sanctum')->postJson('/api/reservations', $payload)->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/reservations', array_merge($payload, ['title' => '冲突测试B']))
            ->assertStatus(400);
    }
}
