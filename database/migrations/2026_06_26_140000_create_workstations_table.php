<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workstations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_settings_id')->nullable()->constrained('shop_settings')->nullOnDelete();
            $table->string('name', 128);
            $table->string('location_label', 128)->nullable();
            $table->foreignId('current_operator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_operator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['shop_settings_id', 'is_active'], 'ws_shop_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workstations');
    }
};
