<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            if (!Schema::hasColumn('approvals', 'process_instance_id')) {
                $table->string('process_instance_id', 100)->nullable()->after('callback_url')
                    ->comment('IM审批实例ID');
                $table->index(['process_instance_id', 'tenant_id']);
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'im_department_id')) {
                $table->string('im_department_id', 100)->nullable()->after('code')
                    ->comment('IM平台部门ID');
                $table->index(['tenant_id', 'im_department_id']);
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'dingtalk_callback_token')) {
                $table->string('dingtalk_callback_token', 100)->nullable()->after('dingtalk_process_code')
                    ->comment('钉钉事件回调Token');
            }
            if (!Schema::hasColumn('tenants', 'dingtalk_callback_aes_key')) {
                $table->string('dingtalk_callback_aes_key', 100)->nullable()->after('dingtalk_callback_token')
                    ->comment('钉钉事件回调EncodingAESKey');
            }
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            if (Schema::hasColumn('approvals', 'process_instance_id')) {
                $table->dropIndex(['process_instance_id', 'tenant_id']);
                $table->dropColumn('process_instance_id');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'im_department_id')) {
                $table->dropIndex(['tenant_id', 'im_department_id']);
                $table->dropColumn('im_department_id');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            $columns = ['dingtalk_callback_token', 'dingtalk_callback_aes_key'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
