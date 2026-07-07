<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['tenant_id', 'code'], 'departments_tenant_code_unique');
        });

        Schema::table('meeting_rooms', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['tenant_id', 'code'], 'meeting_rooms_tenant_code_unique');
        });

        Schema::table('device_tags', function (Blueprint $table) {
            if (!Schema::hasColumn('device_tags', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')
                    ->comment('租户ID，null为系统预设标签');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('device_tags', function (Blueprint $table) {
            if (Schema::hasColumn('device_tags', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id', 'status']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('meeting_rooms', function (Blueprint $table) {
            $table->dropUnique('meeting_rooms_tenant_code_unique');
            $table->unique('code');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_tenant_code_unique');
            $table->unique('code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_email_unique');
            $table->unique('email');
        });
    }
};
