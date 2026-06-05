<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run()
    {
        Department::create([
            'name' => '技术部',
            'code' => 'tech',
            'parent_id' => null,
            'status' => 1,
            'sort_order' => 1,
        ]);

        Department::create([
            'name' => '产品部',
            'code' => 'product',
            'parent_id' => null,
            'status' => 1,
            'sort_order' => 2,
        ]);

        Department::create([
            'name' => '市场部',
            'code' => 'marketing',
            'parent_id' => null,
            'status' => 1,
            'sort_order' => 3,
        ]);

        Department::create([
            'name' => '人力资源部',
            'code' => 'hr',
            'parent_id' => null,
            'status' => 1,
            'sort_order' => 4,
        ]);

        Department::create([
            'name' => '财务部',
            'code' => 'finance',
            'parent_id' => null,
            'status' => 1,
            'sort_order' => 5,
        ]);
    }
}