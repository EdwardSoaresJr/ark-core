<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('estimate_documents')
            ->whereNotNull('pdf_path')
            ->update([
                'needs_pdf_refresh' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // PDFs may have been regenerated with the updated template; do not revert artifacts.
    }
};
