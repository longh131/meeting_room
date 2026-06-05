<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('用户姓名');
            $table->string('email', 100)->unique()->comment('邮箱');
            $table->string('password', 255)->comment('密码');
            $table->string('phone', 20)->nullable()->comment('手机号');
            $table->foreignId('department_id')->nullable()->comment('部门ID');
            $table->string('position', 50)->nullable()->comment('职位');
            $table->string('dingtalk_user_id', 64)->nullable()->comment('钉钉用户ID（预留）');
            $table->string('feishu_open_id', 64)->nullable()->comment('飞书OpenID（预留）');
            $table->string('wework_user_id', 64)->nullable()->comment('企业微信用户ID（预留）');
            $table->tinyInteger('is_manager')->default(0)->comment('是否部门经理 0-否 1-是');
            $table->tinyInteger('is_admin')->default(0)->comment('是否管理员 0-否 1-是');
            $table->integer('credit_score')->default(100)->comment('信用分（初始100）');
            $table->string('avatar', 255)->nullable()->comment('头像URL');
            $table->tinyInteger('status')->default(1)->comment('状态 0-禁用 1-启用');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->index(['email', 'status']);
            $table->index(['department_id', 'is_manager']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};