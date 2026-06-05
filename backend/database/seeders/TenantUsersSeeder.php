<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantUsersSeeder extends Seeder
{
    public function run($tenantIds)
    {
        foreach ($tenantIds as $tenantId) {
            $companyName = $tenantId == 1 ? '示例科技' : '创新科技';

            $dept1 = Department::create([
                'tenant_id' => $tenantId,
                'name' => '技术部',
                'code' => ($tenantId == 1 ? 'TECH1' : 'TECH2'),
                'status' => 1,
                'sort_order' => 1,
            ]);

            $dept2 = Department::create([
                'tenant_id' => $tenantId,
                'name' => '产品部',
                'code' => ($tenantId == 1 ? 'PROD1' : 'PROD2'),
                'status' => 1,
                'sort_order' => 2,
            ]);

            $dept3 = Department::create([
                'tenant_id' => $tenantId,
                'name' => '市场部',
                'code' => ($tenantId == 1 ? 'MKT1' : 'MKT2'),
                'status' => 1,
                'sort_order' => 3,
            ]);

            User::create([
                'tenant_id' => $tenantId,
                'name' => $companyName . '-管理员',
                'email' => ($tenantId == 1 ? 'admin@demo.com' : 'admin@inno.com'),
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
                'tenant_id' => $tenantId,
                'name' => $companyName . '-张三',
                'email' => ($tenantId == 1 ? 'zhangsan@demo.com' : 'zhangsan@inno.com'),
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
                'tenant_id' => $tenantId,
                'name' => $companyName . '-李四',
                'email' => ($tenantId == 1 ? 'lisi@demo.com' : 'lisi@inno.com'),
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
                'tenant_id' => $tenantId,
                'name' => $companyName . '-王五',
                'email' => ($tenantId == 1 ? 'wangwu@demo.com' : 'wangwu@inno.com'),
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
                'tenant_id' => $tenantId,
                'name' => $companyName . '-赵六',
                'email' => ($tenantId == 1 ? 'zhaoliu@demo.com' : 'zhaoliu@inno.com'),
                'password' => Hash::make('123456'),
                'department_id' => $dept3->id,
                'position' => '市场经理',
                'is_manager' => 1,
                'is_admin' => 0,
                'is_super_admin' => 0,
                'credit_score' => 100,
                'status' => 1,
            ]);

            $this->command->info("Created users for tenant {$tenantId}: {$companyName}");
        }
    }
}
