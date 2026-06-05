<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id', 'email']);
                $table->index(['tenant_id', 'status']);
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id']);
            }
        });

        Schema::table('meeting_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('meeting_rooms', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id', 'floor', 'status']);
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id', 'meeting_room_id', 'start_time']);
                $table->index(['tenant_id', 'user_id', 'status']);
            }
        });

        Schema::table('reservation_attendees', function (Blueprint $table) {
            if (!Schema::hasColumn('reservation_attendees', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id']);
            }
        });

        Schema::table('approvals', function (Blueprint $table) {
            if (!Schema::hasColumn('approvals', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id', 'status']);
            }
        });

        Schema::table('favorites', function (Blueprint $table) {
            if (!Schema::hasColumn('favorites', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id']);
            }
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_logs', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id']);
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id', 'email']);
                $table->dropIndex(['tenant_id', 'status']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('meeting_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_rooms', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id', 'floor', 'status']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id', 'meeting_room_id', 'start_time']);
                $table->dropIndex(['tenant_id', 'user_id', 'status']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('reservation_attendees', function (Blueprint $table) {
            if (Schema::hasColumn('reservation_attendees', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('approvals', function (Blueprint $table) {
            if (Schema::hasColumn('approvals', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id', 'status']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('favorites', function (Blueprint $table) {
            if (Schema::hasColumn('favorites', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            if (Schema::hasColumn('notification_logs', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};
