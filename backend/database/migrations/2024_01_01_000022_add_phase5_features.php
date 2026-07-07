<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'credit_min_threshold')) {
                $table->unsignedSmallInteger('credit_min_threshold')->default(60)->after('approval_max_reminds')
                    ->comment('低于此分数限制预定');
            }
            if (!Schema::hasColumn('tenants', 'credit_checkin_bonus')) {
                $table->unsignedSmallInteger('credit_checkin_bonus')->default(1)->after('credit_min_threshold')
                    ->comment('准时签到加分');
            }
            if (!Schema::hasColumn('tenants', 'waitlist_confirm_minutes')) {
                $table->unsignedSmallInteger('waitlist_confirm_minutes')->default(30)->after('credit_checkin_bonus')
                    ->comment('候补通知后确认时限(分钟)');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'calendar_feed_token')) {
                $table->string('calendar_feed_token', 64)->nullable()->unique()->after('source')
                    ->comment('个人日历 ICS 订阅 token');
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'google_event_id')) {
                $table->string('google_event_id')->nullable()->after('booked_by_user_id');
            }
            if (!Schema::hasColumn('reservations', 'outlook_event_id')) {
                $table->string('outlook_event_id')->nullable()->after('google_event_id');
            }
        });

        if (!Schema::hasTable('credit_logs')) {
            Schema::create('credit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->integer('change_amount');
                $table->integer('balance_after');
                $table->string('reason', 200);
                $table->unsignedBigInteger('reservation_id')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index('tenant_id');
            });
        }

        if (!Schema::hasTable('calendar_connections')) {
            Schema::create('calendar_connections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->string('provider', 20)->comment('google|outlook');
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('calendar_id')->nullable();
                $table->string('account_email')->nullable();
                $table->boolean('sync_enabled')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'provider']);
            });
        }

        if (!Schema::hasTable('reservation_waitlist')) {
            Schema::create('reservation_waitlist', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('meeting_room_id');
                $table->string('title')->nullable();
                $table->dateTime('start_time');
                $table->dateTime('end_time');
                $table->unsignedTinyInteger('status')->default(0)
                    ->comment('0排队中 1已通知 2已确认 3已过期 4已取消');
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['meeting_room_id', 'start_time', 'status'], 'waitlist_room_time_status_idx');
            });
        }

        if (!Schema::hasTable('webhook_endpoints')) {
            Schema::create('webhook_endpoints', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name', 100);
                $table->string('url', 500);
                $table->string('secret', 64)->nullable();
                $table->json('events');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tenant_api_tokens')) {
            Schema::create('tenant_api_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name', 100);
                $table->string('token_prefix', 12);
                $table->string('token_hash', 64);
                $table->json('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['token_prefix', 'token_hash']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_tokens');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('reservation_waitlist');
        Schema::dropIfExists('calendar_connections');
        Schema::dropIfExists('credit_logs');

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['google_event_id', 'outlook_event_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('calendar_feed_token');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['credit_min_threshold', 'credit_checkin_bonus', 'waitlist_confirm_minutes']);
        });
    }
};
