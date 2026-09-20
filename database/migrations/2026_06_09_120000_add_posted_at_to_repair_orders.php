<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->timestamp('posted_at')->nullable()->after('closed_at');
            $table->index('posted_at', 'ro_posted_at_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("
                UPDATE repair_orders ro
                INNER JOIN estimate_documents ed
                    ON ed.repair_order_id = ro.id
                    AND ed.document_type = 'invoice'
                    AND ed.status = 'paid'
                SET ro.posted_at = COALESCE(ro.closed_at, ro.updated_at)
                WHERE ro.posted_at IS NULL
                    AND ro.status = 'closed'
                    AND ro.close_variant_key = 'paid'
            ");
        }
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->dropIndex('ro_posted_at_idx');
            $table->dropColumn('posted_at');
        });
    }
};
