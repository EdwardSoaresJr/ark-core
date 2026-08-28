<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->unsignedInteger('policy_resolved_labor_rate_cents')->nullable()->after('labor_rate_cents');
            $table->timestamp('labor_rate_overridden_at')->nullable()->after('labor_rate_override_reason');
            $table->foreignId('labor_rate_overridden_by_user_id')
                ->nullable()
                ->after('labor_rate_overridden_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('labor_rate_overridden_by_user_id');
            $table->dropColumn([
                'policy_resolved_labor_rate_cents',
                'labor_rate_overridden_at',
            ]);
        });
    }
};
