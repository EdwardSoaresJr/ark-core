<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('issued_at');
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('due_at');
            $table->timestamp('posted_to_receivables_at')->nullable()->after('payment_terms_days');
            $table->index(['document_type', 'status', 'due_at'], 'estimate_docs_ar_due_idx');
            $table->index('posted_to_receivables_at', 'estimate_docs_ar_posted_idx');
        });

        // Catalog rows are present after status seed/sync; skip cleanly during early migrate.
        if (DB::table('ro_statuses')->where('slug', 'closed')->exists()) {
            DB::table('ro_status_variants')->updateOrInsert(
                [
                    'status_slug' => 'closed',
                    'variant_key' => 'on_account',
                ],
                [
                    'name' => 'On account',
                    'affects_metrics' => true,
                    'bypass_standard_close_rules' => false,
                    'is_default' => false,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            DB::table('ro_status_variants')
                ->where('status_slug', 'closed')
                ->where('variant_key', 'lost')
                ->update(['sort_order' => 2, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ro_status_variants')) {
            DB::table('ro_status_variants')
                ->where('status_slug', 'closed')
                ->where('variant_key', 'on_account')
                ->delete();

            DB::table('ro_status_variants')
                ->where('status_slug', 'closed')
                ->where('variant_key', 'lost')
                ->update(['sort_order' => 1, 'updated_at' => now()]);
        }

        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->dropIndex('estimate_docs_ar_due_idx');
            $table->dropIndex('estimate_docs_ar_posted_idx');
            $table->dropColumn(['due_at', 'payment_terms_days', 'posted_to_receivables_at']);
        });
    }
};
