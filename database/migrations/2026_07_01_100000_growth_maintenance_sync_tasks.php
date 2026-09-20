<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_sync_tasks', function (Blueprint $table): void {
            $table->string('task_key', 64)->primary();
            $table->string('status', 16);
            $table->timestamp('last_ran_at')->nullable();
            $table->text('last_message')->nullable();
            $table->json('last_metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_sync_tasks');
    }
};
