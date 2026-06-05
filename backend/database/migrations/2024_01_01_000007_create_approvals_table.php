<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->comment('预定ID');
            $table->foreignId('approver_id')->comment('审批人ID');
            $table->tinyInteger('status')->default(0)->comment('状态 0-待审批 1-已通过 2-已驳回');
            $table->text('comment')->nullable()->comment('审批意见');
            $table->datetime('approved_at')->nullable()->comment('审批时间');
            $table->string('callback_url')->nullable()->comment('回调URL（预留用于IM审批）');
            $table->timestamps();
            
            $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['reservation_id', 'approver_id']);
            $table->index(['approver_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('approvals');
    }
};