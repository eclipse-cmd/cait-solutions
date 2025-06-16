<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('telegram_group_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('telegram_groups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('telegram_bot_users')->onDelete('cascade');
            $table->boolean('notifications_enabled')->default(true);
            $table->timestamp('joined_at')->useCurrent();
            $table->string('role')->default('member');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
            $table->index(['group_id', 'is_active']);
            $table->index(['user_id', 'notifications_enabled']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('telegram_group_users');
    }
};
