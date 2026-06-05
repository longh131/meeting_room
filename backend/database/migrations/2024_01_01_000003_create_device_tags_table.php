<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->comment('设备名称');
            $table->string('icon', 50)->nullable()->comment('图标标识');
            $table->tinyInteger('status')->default(1)->comment('状态 0-禁用 1-启用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_tags');
    }
};