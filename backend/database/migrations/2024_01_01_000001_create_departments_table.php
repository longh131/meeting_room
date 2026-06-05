<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('部门名称');
            $table->foreignId('parent_id')->nullable()->comment('上级部门ID');
            $table->string('code', 30)->unique()->comment('部门编码');
            $table->tinyInteger('status')->default(1)->comment('状态 0-禁用 1-启用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('parent_id')->references('id')->on('departments')->onDelete('set null');
            $table->index(['parent_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('departments');
    }
};