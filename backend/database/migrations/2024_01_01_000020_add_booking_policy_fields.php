<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('max_duration_minutes')->default(240)->after('im_notification_channel')
                ->comment('单次预定最长时长(分钟)');
            $table->unsignedInteger('max_advance_days')->default(30)->comment('最多提前预定天数');
            $table->unsignedInteger('min_advance_minutes')->default(15)->comment('最少提前预定分钟数');
            $table->unsignedInteger('max_daily_bookings_per_user')->default(5)->comment('每人每日预定上限,0=不限');
            $table->unsignedTinyInteger('booking_start_hour')->default(8)->comment('可预定开始小时');
            $table->unsignedTinyInteger('booking_end_hour')->default(22)->comment('可预定结束小时');
            $table->unsignedInteger('no_show_grace_minutes')->default(15)->comment('未签到释放宽限(分钟)');
            $table->unsignedInteger('no_show_deduct_credit')->default(10)->comment('爽约扣分');
            $table->string('meeting_remind_minutes', 50)->default('15,5')->comment('会前提醒分钟,逗号分隔');
            $table->unsignedInteger('approval_remind_hours')->default(2)->comment('审批超时催办间隔(小时)');
            $table->unsignedTinyInteger('approval_max_reminds')->default(3)->comment('审批最大催办次数');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->json('remind_sent')->nullable()->after('checkin_time')
                ->comment('已发送的会前提醒,如{"15":true}');
        });

        Schema::table('approvals', function (Blueprint $table) {
            $table->unsignedTinyInteger('remind_count')->default(0)->after('approved_at');
            $table->datetime('last_reminded_at')->nullable()->after('remind_count');
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropColumn(['remind_count', 'last_reminded_at']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('remind_sent');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'max_duration_minutes', 'max_advance_days', 'min_advance_minutes',
                'max_daily_bookings_per_user', 'booking_start_hour', 'booking_end_hour',
                'no_show_grace_minutes', 'no_show_deduct_credit', 'meeting_remind_minutes',
                'approval_remind_hours', 'approval_max_reminds',
            ]);
        });
    }
};
