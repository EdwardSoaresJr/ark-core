<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->timestamp('customer_presented_at')->nullable()->after('pdf_refreshed_at');
            $table->json('snapshot_revisions_json')->nullable()->after('snapshot_json');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->dropColumn(['customer_presented_at', 'snapshot_revisions_json']);
        });
    }
};
