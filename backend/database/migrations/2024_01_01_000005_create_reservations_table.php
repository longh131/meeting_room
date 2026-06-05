<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_room_id')->comment('会议室ID');
            $table->foreignId('user_id')->comment('预定人ID');
            $table->string('title', 100)->comment('会议主题');
            $table->text('description')->nullable()->comment('会议描述');
            $table->datetime('start_time')->comment('开始时间');
            $table->datetime('end_time')->comment('结束时间');
            $table->tinyInteger('status')->default(0)->comment('状态 0-待审批 1-已预定 2-签到进行中 3-已结束 4-已爽约 5-已取消');
            $table->string('repeat_type', 20)->nullable()->comment('重复类型 daily/weekly/monthly/biweekly');
            $table->date('repeat_end_date')->nullable()->comment('重复结束日期');
            $table->integer('repeat_count')->nullable()->comment('重复次数');
            $table->foreignId('parent_id')->nullable()->comment('父预定ID（周期性会议）');
            $table->tinyInteger('need_approval')->default(1)->comment('是否需要审批 0-否 1-是');
            $table->string('qr_code', 255)->nullable()->comment('签到二维码');
            $table->datetime('checkin_time')->nullable()->comment('签到时间');
            $table->integer('actual_attendees')->default(0)->comment('实际签到人数');
            $table->tinyInteger('is_extended')->default(0)->comment('是否已延长');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('meeting_room_id')->references('id')->on('meeting_rooms')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('reservations')->onDelete('cascade');
            $table->index(['meeting_room_id', 'start_time', 'end_time']);
            $table->index(['user_id', 'status']);
            $table->index(['start_time', 'end_time']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('reservations');
    }
};