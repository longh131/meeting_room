<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 30)->comment('通知渠道 dingtalk/feishu/wework/email/sms/log');
            $table->foreignId('user_id')->comment('目标用户ID');
            $table->string('type', 50)->comment('通知类型');
            $table->text('content')->comment('通知内容');
            $table->tinyInteger('status')->default(0)->comment('发送状态 0-待发送 1-已发送 2-发送失败');
            $table->text('error')->nullable()->comment('错误信息');
            $table->string('callback_data')->nullable()->comment('回调数据（预留）');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['channel', 'status']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_logs');
    }
};