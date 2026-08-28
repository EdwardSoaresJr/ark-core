<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->foreignId('operation_class_id')->constrained('operation_classes')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'operations_active_sort_idx');
        });

        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->foreignId('operation_id')
                ->nullable()
                ->after('labor_category_key')
                ->constrained('operations')
                ->nullOnDelete();
        });

        $classIds = DB::table('operation_classes')->pluck('id', 'key');
        $now = now();

        // Temporary operational records so Pricing can resolve Operation Class.
        // NOT a service catalog. Expand only when another capability earns it.
        // Codes mirror today's labor categories during migration — that is intentional and temporary.
        $rows = [
            ['code' => 'mechanical', 'name' => 'General Mechanical', 'class' => 'general_repair', 'sort' => 10],
            ['code' => 'diagnostic', 'name' => 'Diagnostics', 'class' => 'diagnostics', 'sort' => 20],
            ['code' => 'programming', 'name' => 'Programming', 'class' => 'diagnostics', 'sort' => 30],
            ['code' => 'fabrication', 'name' => 'Fabrication', 'class' => 'advanced_mechanical', 'sort' => 40],
            ['code' => 'courtesy', 'name' => 'Courtesy', 'class' => 'general_repair', 'sort' => 50],
            ['code' => 'comeback', 'name' => 'Comeback', 'class' => 'general_repair', 'sort' => 60],
            ['code' => 'repairpal', 'name' => 'RepairPal', 'class' => 'general_repair', 'sort' => 70],
            ['code' => 'warranty-other', 'name' => 'Warranty — Other', 'class' => 'general_repair', 'sort' => 80],
            ['code' => 'maintenance', 'name' => 'Maintenance', 'class' => 'maintenance', 'sort' => 5],
            ['code' => 'advanced_mechanical', 'name' => 'Advanced Mechanical', 'class' => 'advanced_mechanical', 'sort' => 45],
        ];

        foreach ($rows as $row) {
            if (! isset($classIds[$row['class']])) {
                continue;
            }

            DB::table('operations')->insert([
                'code' => $row['code'],
                'name' => $row['name'],
                'operation_class_id' => $classIds[$row['class']],
                'is_active' => true,
                'sort_order' => $row['sort'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operation_id');
        });

        Schema::dropIfExists('operations');
    }
};
