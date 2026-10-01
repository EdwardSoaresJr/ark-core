<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_work_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->foreignId('repair_order_concern_id')->constrained('repair_order_concerns')->cascadeOnDelete();
            $table->json('line_ids');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('repair_order_concern_id', 'approved_work_scopes_concern_idx');
        });

        Schema::create('authorization_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->foreignId('repair_order_concern_id')->constrained('repair_order_concerns')->cascadeOnDelete();
            $table->string('reason', 32);
            $table->text('note');
            $table->json('line_ids');
            $table->boolean('establishes_customer_consent')->default(false);
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('repair_order_concern_id', 'authorization_exceptions_concern_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_exceptions');
        Schema::dropIfExists('approved_work_scopes');
    }
};
