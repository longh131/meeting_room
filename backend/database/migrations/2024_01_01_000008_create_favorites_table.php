<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->comment('用户ID');
            $table->foreignId('meeting_room_id')->comment('会议室ID');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('meeting_room_id')->references('id')->on('meeting_rooms')->onDelete('cascade');
            $table->unique(['user_id', 'meeting_room_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('favorites');
    }
};