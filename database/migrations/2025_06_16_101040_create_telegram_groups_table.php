<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('telegram_groups', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->unique();
            $table->string('title');
            $table->string('type')->default('group');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->string('invite_link')->nullable();
            $table->timestamps();
            $table->index(['chat_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('telegram_groups');
    }
};
