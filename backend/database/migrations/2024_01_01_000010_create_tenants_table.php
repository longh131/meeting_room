<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('租户名称/公司名');
            $table->string('domain', 100)->unique()->nullable()->comment('独立域名');
            $table->string('contact_name', 50)->nullable()->comment('联系人姓名');
            $table->string('contact_phone', 20)->nullable()->comment('联系人电话');
            $table->string('contact_email', 100)->nullable()->comment('联系人邮箱');
            $table->tinyInteger('status')->default(1)->comment('状态 0-停用 1-正常');
            $table->date('subscription_until')->nullable()->comment('订阅到期日期');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status']);
            $table->index(['domain']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tenants');
    }
};
