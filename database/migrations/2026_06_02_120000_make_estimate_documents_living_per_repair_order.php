<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateRepairOrderIds = DB::table('estimate_documents')
            ->select('repair_order_id')
            ->groupBy('repair_order_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('repair_order_id');

        foreach ($duplicateRepairOrderIds as $repairOrderId) {
            $keepId = DB::table('estimate_documents')
                ->where('repair_order_id', $repairOrderId)
                ->orderByDesc('document_number')
                ->orderByDesc('id')
                ->value('id');

            DB::table('estimate_documents')
                ->where('repair_order_id', $repairOrderId)
                ->where('id', '!=', $keepId)
                ->delete();

            DB::table('estimate_documents')
                ->where('id', $keepId)
                ->update(['document_number' => 1]);
        }

        DB::table('estimate_documents')->update(['document_number' => 1]);

        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->boolean('needs_pdf_refresh')->default(false)->after('generated_at');
            $table->timestamp('refreshed_at')->nullable()->after('needs_pdf_refresh');
            $table->timestamp('pdf_refreshed_at')->nullable()->after('refreshed_at');
            $table->unique('repair_order_id', 'estimate_docs_ro_unique');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->dropUnique('estimate_docs_ro_unique');
            $table->dropColumn(['needs_pdf_refresh', 'refreshed_at', 'pdf_refreshed_at']);
        });
    }
};
