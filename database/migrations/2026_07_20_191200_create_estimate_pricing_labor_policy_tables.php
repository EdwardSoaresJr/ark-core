<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_classes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('labor_policies', function (Blueprint $table) {
            $table->id();
            $table->string('billing_posture', 24);
            $table->foreignId('operation_class_id')->constrained('operation_classes')->cascadeOnDelete();
            $table->string('rate_type', 32);
            $table->unsignedInteger('hourly_rate_cents')->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(
                ['billing_posture', 'operation_class_id', 'effective_from', 'priority'],
                'labor_policies_resolve_idx',
            );
        });

        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->string('resolved_from_posture', 24)->nullable()->after('labor_rate_cents');
            $table->string('resolved_from_operation_class', 64)->nullable()->after('resolved_from_posture');
            $table->foreignId('labor_policy_id')->nullable()->after('resolved_from_operation_class')
                ->constrained('labor_policies')->nullOnDelete();
            $table->unsignedInteger('labor_policy_version')->nullable()->after('labor_policy_id');
            $table->string('labor_rate_override_reason', 64)->nullable()->after('labor_policy_version');
        });

        $now = now();

        $classes = [
            ['key' => 'maintenance', 'name' => 'Maintenance', 'sort_order' => 10],
            ['key' => 'general_repair', 'name' => 'General Repair', 'sort_order' => 20],
            ['key' => 'advanced_mechanical', 'name' => 'Advanced Mechanical', 'sort_order' => 30],
            ['key' => 'diagnostics', 'name' => 'Diagnostics', 'sort_order' => 40],
        ];

        foreach ($classes as $class) {
            DB::table('operation_classes')->insert([
                ...$class,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $classIds = DB::table('operation_classes')->pluck('id', 'key');
        $defaultRateCents = (int) (DB::table('shop_settings')->value('default_labor_rate_cents') ?: 16500);
        $effectiveFrom = '2000-01-01';

        $retailRates = [
            'maintenance' => $defaultRateCents,
            'general_repair' => $defaultRateCents,
            'advanced_mechanical' => $defaultRateCents,
            'diagnostics' => $defaultRateCents,
        ];

        foreach ($retailRates as $classKey => $rateCents) {
            DB::table('labor_policies')->insert([
                'billing_posture' => 'customer_pay',
                'operation_class_id' => $classIds[$classKey],
                'rate_type' => 'hourly',
                'hourly_rate_cents' => $rateCents,
                'effective_from' => $effectiveFrom,
                'effective_until' => null,
                'priority' => 100,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (['fleet', 'wholesale'] as $posture) {
            foreach ($classIds as $classId) {
                DB::table('labor_policies')->insert([
                    'billing_posture' => $posture,
                    'operation_class_id' => $classId,
                    'rate_type' => 'hourly',
                    'hourly_rate_cents' => $defaultRateCents,
                    'effective_from' => $effectiveFrom,
                    'effective_until' => null,
                    'priority' => 100,
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($classIds as $classId) {
            DB::table('labor_policies')->insert([
                'billing_posture' => 'internal',
                'operation_class_id' => $classId,
                'rate_type' => 'zero',
                'hourly_rate_cents' => 0,
                'effective_from' => $effectiveFrom,
                'effective_until' => null,
                'priority' => 100,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('labor_policies')->insert([
                'billing_posture' => 'comeback',
                'operation_class_id' => $classId,
                'rate_type' => 'zero',
                'hourly_rate_cents' => 0,
                'effective_from' => $effectiveFrom,
                'effective_until' => null,
                'priority' => 100,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('labor_policies')->insert([
                'billing_posture' => 'warranty_other',
                'operation_class_id' => $classId,
                'rate_type' => 'contract',
                'hourly_rate_cents' => $defaultRateCents,
                'effective_from' => $effectiveFrom,
                'effective_until' => null,
                'priority' => 100,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('labor_policies')->insert([
                'billing_posture' => 'repairpal',
                'operation_class_id' => $classId,
                'rate_type' => 'contract',
                'hourly_rate_cents' => 15000,
                'effective_from' => $effectiveFrom,
                'effective_until' => null,
                'priority' => 100,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('labor_policy_id');
            $table->dropColumn([
                'resolved_from_posture',
                'resolved_from_operation_class',
                'labor_policy_version',
                'labor_rate_override_reason',
            ]);
        });

        Schema::dropIfExists('labor_policies');
        Schema::dropIfExists('operation_classes');
    }
};
