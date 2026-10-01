<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workstation_browser_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workstation_id')->constrained('workstations')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('workstation_id', 'ws_browser_ws_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workstation_browser_bindings');
    }
};
