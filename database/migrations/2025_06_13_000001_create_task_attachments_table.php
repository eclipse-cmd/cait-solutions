<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('task_attachments', function (Blueprint $table) {
      $table->id();
      $table->foreignId('task_id')->constrained()->onDelete('cascade');
      $table->string('file_id');
      $table->string('file_type')->nullable();
      $table->string('file_name')->nullable();
      $table->string('file_url')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('task_attachments');
  }
};
