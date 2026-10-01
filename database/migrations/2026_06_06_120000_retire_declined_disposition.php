<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('repair_order_concerns')
            ->where('disposition', 'declined')
            ->update(['disposition' => 'deferred']);

        DB::table('approval_events')
            ->where('approval_type', 'declined')
            ->update(['approval_type' => 'partial']);
    }

    public function down(): void
    {
        // Declined is retired; do not restore ambiguous historical splits.
    }
};
