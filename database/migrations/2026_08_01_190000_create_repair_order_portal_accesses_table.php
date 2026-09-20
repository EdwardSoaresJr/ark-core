<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_order_portal_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->string('public_code', 16);
            $table->string('token_hash', 64);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('first_customer_viewed_at')->nullable();
            $table->timestamps();

            $table->unique('public_code', 'ro_portal_access_code_uq');
            $table->unique('token_hash', 'ro_portal_access_token_uq');
            $table->index(['repair_order_id', 'revoked_at'], 'ro_portal_access_ro_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_order_portal_accesses');
    }
};
