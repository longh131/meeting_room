<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run()
    {
        $tenant1 = Tenant::create([
            'name' => '示例科技有限公司',
            'domain' => 'demo.sisuu.com',
            'contact_name' => '李明',
            'contact_phone' => '13800138001',
            'contact_email' => 'contact@demo.sisuu.com',
            'status' => 1,
            'subscription_until' => '2027-12-31',
        ]);

        $tenant2 = Tenant::create([
            'name' => '创新科技有限公司',
            'domain' => 'inno.sisuu.com',
            'contact_name' => '王华',
            'contact_phone' => '13900139002',
            'contact_email' => 'contact@inno.sisuu.com',
            'status' => 1,
            'subscription_until' => '2027-12-31',
        ]);

        $this->command->info("Created tenant: {$tenant1->name} (ID: {$tenant1->id})");
        $this->command->info("Created tenant: {$tenant2->name} (ID: {$tenant2->id})");

        return [$tenant1->id, $tenant2->id];
    }
}
