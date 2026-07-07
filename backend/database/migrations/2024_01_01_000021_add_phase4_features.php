<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('type', 50)->comment('通知类型');
            $table->string('name', 100)->comment('模板名称');
            $table->text('body')->comment('模板内容，支持 {变量}');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'type']);
            $table->index('tenant_id');
        });

        Schema::create('room_blackouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('meeting_room_id');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('reason')->nullable();
            $table->string('repeat_type', 20)->default('none')->comment('none|weekly');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['meeting_room_id', 'start_time', 'end_time']);
            $table->index('tenant_id');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('booked_by_user_id')->nullable()->after('user_id')
                ->comment('代预定操作人，null表示本人预定');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('booked_by_user_id');
        });
        Schema::dropIfExists('room_blackouts');
        Schema::dropIfExists('message_templates');
    }
};
