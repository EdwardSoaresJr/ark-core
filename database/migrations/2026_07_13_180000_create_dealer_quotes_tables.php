<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->string('supplier_name', 191)->nullable();
            $table->string('quote_number', 64)->nullable();
            $table->string('vehicle_description', 191)->nullable();
            $table->string('vin', 32)->nullable();
            $table->unsignedInteger('dealer_total_cents')->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->longText('raw_text')->nullable();
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['repair_order_id', 'captured_at'], 'dealer_quotes_ro_captured_idx');
        });

        Schema::create('dealer_quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_quote_id')->constrained('dealer_quotes')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->decimal('quantity', 8, 2);
            $table->string('part_number', 64)->nullable();
            $table->string('description', 255);
            $table->unsignedInteger('unit_cost_cents');
            $table->unsignedInteger('extended_cost_cents')->nullable();
            $table->timestamps();

            $table->index(['dealer_quote_id', 'position'], 'dealer_quote_lines_quote_pos_idx');
        });

        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->foreignId('dealer_quote_line_id')
                ->nullable()
                ->after('part_number')
                ->constrained('dealer_quote_lines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dealer_quote_line_id');
        });

        Schema::dropIfExists('dealer_quote_lines');
        Schema::dropIfExists('dealer_quotes');
    }
};
