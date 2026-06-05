<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reservation_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->comment('预定ID');
            $table->foreignId('user_id')->comment('参会人ID');
            $table->tinyInteger('status')->default(0)->comment('状态 0-未确认 1-已确认 2-已签到 3-缺席');
            $table->timestamps();
            
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['reservation_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('reservation_attendees');
    }
};