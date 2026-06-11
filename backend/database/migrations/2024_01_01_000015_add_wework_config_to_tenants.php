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
            // 企业微信配置
            $table->boolean('wework_enabled')->default(false)->comment('是否启用企业微信集成');
            $table->string('wework_corp_id')->nullable()->comment('企业微信企业CorpId');
            $table->string('wework_secret')->nullable()->comment('企业微信应用Secret');
            $table->string('wework_agent_id')->nullable()->comment('企业微信应用AgentId');
            $table->string('wework_token')->nullable()->comment('企业微信回调Token');
            $table->string('wework_encoding_aes_key')->nullable()->comment('企业微信回调EncodingAESKey');
            $table->string('wework_approval_code')->nullable()->comment('企业微信审批模板Code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'wework_enabled',
                'wework_corp_id',
                'wework_secret',
                'wework_agent_id',
                'wework_token',
                'wework_encoding_aes_key',
                'wework_approval_code',
            ]);
        });
    }
};