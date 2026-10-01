<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_order_concern_id')->constrained('repair_order_concerns')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->integer('unit_price_cents')->default(0);
            $table->integer('part_cost_cents')->nullable();
            $table->integer('matrix_suggested_price_cents')->nullable();
            $table->string('pricing_mode', 32)->nullable();
            $table->string('pricing_matrix_key')->nullable();
            $table->string('pricing_matrix_name')->nullable();
            $table->boolean('matrix_applied')->default(false);
            $table->string('vendor_name')->nullable();
            $table->string('part_number')->nullable();
            $table->integer('subtotal_cents')->default(0);
            $table->integer('tax_cents')->default(0);
            $table->integer('shop_fee_cents')->default(0);
            $table->integer('total_cents')->default(0);
            $table->boolean('is_overridden')->default(false);
            $table->timestamps();

            $table->index(['repair_order_id', 'type'], 'ro_lines_ro_type_idx');
            $table->index(['repair_order_id', 'repair_order_concern_id'], 'ro_lines_ro_concern_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_order_lines');
    }
};
