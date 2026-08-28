<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('document_number');
            $table->json('snapshot_json');
            $table->string('status', 32)->default('draft');
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['repair_order_id', 'document_number'], 'estimate_docs_ro_num_unique');
            $table->index(['repair_order_id', 'status'], 'estimate_docs_ro_status_idx');
            $table->index('created_by', 'estimate_docs_created_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_documents');
    }
};
