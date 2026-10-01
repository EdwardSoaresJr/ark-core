<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->foreignId('encounter_id')
                ->nullable()
                ->after('id')
                ->constrained('encounters')
                ->nullOnDelete();

            $table->index('encounter_id', 'ro_encounter_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropForeign(['encounter_id']);
            $table->dropIndex('ro_encounter_idx');
            $table->dropColumn('encounter_id');
        });
    }
};
