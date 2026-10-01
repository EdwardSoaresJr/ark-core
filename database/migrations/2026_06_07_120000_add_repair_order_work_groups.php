<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_order_work_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_order_concern_id')->constrained('repair_order_concerns')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['repair_order_concern_id', 'position'], 'ro_wg_concern_pos_idx');
        });

        Schema::table('repair_order_lines', function (Blueprint $table): void {
            $table->foreignId('repair_order_work_group_id')
                ->nullable()
                ->after('repair_order_concern_id')
                ->constrained('repair_order_work_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('repair_order_work_group_id');
        });

        Schema::dropIfExists('repair_order_work_groups');
    }
};
