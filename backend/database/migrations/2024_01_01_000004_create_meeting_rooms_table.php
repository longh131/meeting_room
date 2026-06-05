<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('meeting_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('会议室名称');
            $table->string('code', 30)->unique()->comment('会议室编码');
            $table->integer('floor')->default(1)->comment('楼层');
            $table->integer('capacity')->default(4)->comment('容纳人数');
            $table->text('description')->nullable()->comment('描述');
            $table->string('photo', 255)->nullable()->comment('实景照片URL');
            $table->string('floor_plan', 255)->nullable()->comment('平面图URL');
            $table->json('device_tags')->nullable()->comment('设备标签JSON');
            $table->decimal('hourly_rate', 10, 2)->nullable()->comment('小时费率');
            $table->tinyInteger('status')->default(1)->comment('状态 0-禁用 1-启用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['floor', 'status', 'capacity']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('meeting_rooms');
    }
};