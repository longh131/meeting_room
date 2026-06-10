<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 为tenants表添加IM配置字段，支持每个租户独立配置钉钉/飞书
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // 钉钉配置
            $table->boolean('dingtalk_enabled')->default(false)->comment('是否启用钉钉集成');
            $table->string('dingtalk_app_key')->nullable()->comment('钉钉应用AppKey');
            $table->string('dingtalk_app_secret')->nullable()->comment('钉钉应用AppSecret');
            $table->string('dingtalk_corp_id')->nullable()->comment('钉钉企业CorpId');
            $table->string('dingtalk_agent_id')->nullable()->comment('钉钉应用AgentId');
            $table->string('dingtalk_process_code')->nullable()->comment('钉钉审批流程Code');
            
            // 飞书配置
            $table->boolean('feishu_enabled')->default(false)->comment('是否启用飞书集成');
            $table->string('feishu_app_id')->nullable()->comment('飞书应用AppId');
            $table->string('feishu_app_secret')->nullable()->comment('飞书应用AppSecret');
            $table->string('feishu_approval_code')->nullable()->comment('飞书审批定义Code');
            
            // IM通知渠道配置
            $table->string('im_notification_channel')->default('log')->comment('IM通知渠道：log/dingtalk/feishu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'dingtalk_enabled',
                'dingtalk_app_key',
                'dingtalk_app_secret',
                'dingtalk_corp_id',
                'dingtalk_agent_id',
                'dingtalk_process_code',
                'feishu_enabled',
                'feishu_app_id',
                'feishu_app_secret',
                'feishu_approval_code',
                'im_notification_channel',
            ]);
        });
    }
};