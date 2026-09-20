<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ro_status_transitions', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('to_status_slug');
        });

        DB::table('ro_statuses')
            ->whereIn('slug', [
                'approved',
                'waiting_parts',
                'ready_for_work',
                'in_progress',
                'quality_check',
            ])
            ->update(['show_on_technician_board' => true]);
    }

    public function down(): void
    {
        Schema::table('ro_status_transitions', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
