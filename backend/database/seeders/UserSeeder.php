<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name' => '管理员',
            'email' => 'admin@example.com',
            'password' => Hash::make('123456'),
            'department_id' => null,
            'position' => '系统管理员',
            'is_manager' => 0,
            'is_admin' => 1,
            'credit_score' => 100,
            'status' => 1,
        ]);

        User::create([
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'password' => Hash::make('123456'),
            'department_id' => 1,
            'position' => '技术总监',
            'is_manager' => 1,
            'is_admin' => 0,
            'credit_score' => 100,
            'status' => 1,
        ]);

        User::create([
            'name' => '李四',
            'email' => 'lisi@example.com',
            'password' => Hash::make('123456'),
            'department_id' => 1,
            'position' => '高级工程师',
            'is_manager' => 0,
            'is_admin' => 0,
            'credit_score' => 100,
            'status' => 1,
        ]);

        User::create([
            'name' => '王五',
            'email' => 'wangwu@example.com',
            'password' => Hash::make('123456'),
            'department_id' => 2,
            'position' => '产品经理',
            'is_manager' => 1,
            'is_admin' => 0,
            'credit_score' => 100,
            'status' => 1,
        ]);

        User::create([
            'name' => '赵六',
            'email' => 'zhaoliu@example.com',
            'password' => Hash::make('123456'),
            'department_id' => 3,
            'position' => '市场经理',
            'is_manager' => 1,
            'is_admin' => 0,
            'credit_score' => 100,
            'status' => 1,
        ]);
    }
}