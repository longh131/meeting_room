<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // 飞书Verification Token（事件回调验证用）
            $table->string('feishu_verification_token')->nullable()->comment('飞书Verification Token');
            // 飞书应用类型（自建应用/应用商店应用）
            $table->string('feishu_app_type')->default('self')->comment('飞书应用类型：self(自建应用)/store(应用商店应用)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['feishu_verification_token', 'feishu_app_type']);
        });
    }
};
