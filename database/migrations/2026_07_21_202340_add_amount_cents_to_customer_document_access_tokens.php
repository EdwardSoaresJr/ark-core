<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_document_access_tokens', function (Blueprint $table) {
            $table->unsignedInteger('amount_cents')->nullable()->after('scope');
        });

        Schema::table('customer_document_access_tokens', function (Blueprint $table) {
            $table->dropForeign(['financial_document_id']);
        });

        Schema::table('customer_document_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('financial_document_id')->nullable()->change();
            $table->foreign('financial_document_id')
                ->references('id')
                ->on('estimate_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_document_access_tokens', function (Blueprint $table) {
            $table->dropForeign(['financial_document_id']);
        });

        Schema::table('customer_document_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('financial_document_id')->nullable(false)->change();
            $table->foreign('financial_document_id')
                ->references('id')
                ->on('estimate_documents')
                ->cascadeOnDelete();
            $table->dropColumn('amount_cents');
        });
    }
};
