<?php

use App\Ark\Operations\Documents\EstimateDocument;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        EstimateDocument::query()
            ->whereIn('document_type', ['estimate', 'invoice'])
            ->update([
                'needs_pdf_refresh' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
