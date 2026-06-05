<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use App\Models\MeetingRoom;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MultiTenantSeeder extends Seeder
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

        $this->createTenantData($tenant1);
        $this->createTenantData($tenant2);

        $this->command->info('Created 2 tenants with full data');
    }

    private function createTenantData($tenant)
    {
        $companyName = $tenant->id == 1 ? '示例科技' : '创新科技';
        $prefix = $tenant->id == 1 ? 'D' : 'I';

        $dept1 = Department::create([
            'tenant_id' => $tenant->id,
            'name' => '技术部',
            'code' => ($tenant->id == 1 ? 'TECH1' : 'TECH2'),
            'status' => 1,
            'sort_order' => 1,
        ]);

        $dept2 = Department::create([
            'tenant_id' => $tenant->id,
            'name' => '产品部',
            'code' => ($tenant->id == 1 ? 'PROD1' : 'PROD2'),
            'status' => 1,
            'sort_order' => 2,
        ]);

        $dept3 = Department::create([
            'tenant_id' => $tenant->id,
            'name' => '市场部',
            'code' => ($tenant->id == 1 ? 'MKT1' : 'MKT2'),
            'status' => 1,
            'sort_order' => 3,
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => $companyName . '-管理员',
            'email' => ($tenant->id == 1 ? 'admin@demo.com' : 'admin@inno.com'),
            'password' => Hash::make('123456'),
            'department_id' => $dept1->id,
            'position' => '系统管理员',
            'is_manager' => 0,
            'is_admin' => 1,
            'is_super_admin' => 0,
            'credit_score' => 100,
            'status' => 1,
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => $companyName . '-张三',
            'email' => ($tenant->id == 1 ? 'zhangsan@demo.com' : 'zhangsan@inno.com'),
            'password' => Hash::make('123456'),
            'department_id' => $dept1->id,
            'position' => '技术总监',
            'is_manager' => 1,
            'is_admin' => 0,
            'is_super_admin' => 0,
            'credit_score' => 100,
            'status' => 1,
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => $companyName . '-李四',
            'email' => ($tenant->id == 1 ? 'lisi@demo.com' : 'lisi@inno.com'),
            'password' => Hash::make('123456'),
            'department_id' => $dept1->id,
            'position' => '高级工程师',
            'is_manager' => 0,
            'is_admin' => 0,
            'is_super_admin' => 0,
            'credit_score' => 100,
            'status' => 1,
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => $companyName . '-王五',
            'email' => ($tenant->id == 1 ? 'wangwu@demo.com' : 'wangwu@inno.com'),
            'password' => Hash::make('123456'),
            'department_id' => $dept2->id,
            'position' => '产品经理',
            'is_manager' => 1,
            'is_admin' => 0,
            'is_super_admin' => 0,
            'credit_score' => 100,
            'status' => 1,
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => $companyName . '-赵六',
            'email' => ($tenant->id == 1 ? 'zhaoliu@demo.com' : 'zhaoliu@inno.com'),
            'password' => Hash::make('123456'),
            'department_id' => $dept3->id,
            'position' => '市场经理',
            'is_manager' => 1,
            'is_admin' => 0,
            'is_super_admin' => 0,
            'credit_score' => 100,
            'status' => 1,
        ]);

        MeetingRoom::create([
            'tenant_id' => $tenant->id,
            'name' => $companyName . '-创新会议室',
            'code' => $prefix . '-RM-001',
            'floor' => 1,
            'capacity' => 8,
            'description' => '适合小型团队会议，配备投影仪和白板',
            'device_tags' => json_encode([1, 2, 5]),
            'hourly_rate' => 100,
            'status' => 1,
            'sort_order' => 1,
        ]);

        MeetingRoom::create([
            'tenant_id' => $tenant->id,
            'name' => $companyName . '-阳光会议室',
            'code' => $prefix . '-RM-002',
            'floor' => 1,
            'capacity' => 4,
            'description' => '温馨舒适的小型会议室',
            'device_tags' => json_encode([2, 5]),
            'hourly_rate' => 60,
            'status' => 1,
            'sort_order' => 2,
        ]);

        MeetingRoom::create([
            'tenant_id' => $tenant->id,
            'name' => $companyName . '-远景会议室',
            'code' => $prefix . '-RM-003',
            'floor' => 2,
            'capacity' => 12,
            'description' => '中型会议室，适合部门会议',
            'device_tags' => json_encode([1, 2, 3, 5, 6]),
            'hourly_rate' => 150,
            'status' => 1,
            'sort_order' => 3,
        ]);

        MeetingRoom::create([
            'tenant_id' => $tenant->id,
            'name' => $companyName . '-星辰会议室',
            'code' => $prefix . '-RM-004',
            'floor' => 2,
            'capacity' => 20,
            'description' => '大型会议室，配备视频会议系统',
            'device_tags' => json_encode([1, 2, 3, 4, 5, 6, 7, 8]),
            'hourly_rate' => 200,
            'status' => 1,
            'sort_order' => 4,
        ]);

        MeetingRoom::create([
            'tenant_id' => $tenant->id,
            'name' => $companyName . '-静思会议室',
            'code' => $prefix . '-RM-005',
            'floor' => 3,
            'capacity' => 6,
            'description' => '安静私密的小型会议室',
            'device_tags' => json_encode([2, 5, 6]),
            'hourly_rate' => 80,
            'status' => 1,
            'sort_order' => 5,
        ]);

        $this->command->info("Created data for tenant: {$tenant->name}");
    }
}
