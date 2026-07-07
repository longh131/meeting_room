<?php

namespace Tests\Feature;

use App\Models\MeetingRoom;
use App\Models\User;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_tenant_user_only_sees_own_meeting_rooms(): void
    {
        $tenant1Admin = User::where('email', 'admin@demo.com')->first();
        $tenant2Admin = User::where('email', 'admin@inno.com')->first();

        $tenant1Rooms = $this->actingAs($tenant1Admin, 'sanctum')
            ->getJson('/api/meeting-rooms')
            ->json();

        $tenant2Rooms = $this->actingAs($tenant2Admin, 'sanctum')
            ->getJson('/api/meeting-rooms')
            ->json();

        $tenant1Ids = collect($tenant1Rooms)->pluck('id')->sort()->values()->all();
        $tenant2Ids = collect($tenant2Rooms)->pluck('id')->sort()->values()->all();

        $this->assertNotEmpty($tenant1Ids);
        $this->assertNotEmpty($tenant2Ids);
        $this->assertEmpty(array_intersect($tenant1Ids, $tenant2Ids));
    }

    public function test_non_admin_cannot_access_im_config(): void
    {
        $user = User::where('email', 'zhangsan@demo.com')->first();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/tenants/im-config')
            ->assertForbidden();
    }
}
