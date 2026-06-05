<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name' => '超级管理员',
            'email' => 'super@admin.com',
            'password' => Hash::make('123456'),
            'department_id' => null,
            'position' => '系统超级管理员',
            'is_manager' => 0,
            'is_admin' => 1,
            'is_super_admin' => 1,
            'credit_score' => 100,
            'status' => 1,
        ]);

        $this->command->info('Created super admin: super@admin.com (password: 123456)');
    }
}
