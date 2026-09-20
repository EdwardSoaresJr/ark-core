<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dragon_knowledge_sources', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('source_type', 32);
            $table->string('display_name');
            $table->string('authority_class', 64);
            $table->string('origin', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('dragon_knowledge_documents', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('source_id', 32);
            $table->string('title');
            $table->string('source_ref', 512)->nullable();
            $table->longText('body');
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('ingested_at')->nullable();
            $table->timestamps();
            $table->index('source_id');
        });

        Schema::table('dragon_agent_traces', function (Blueprint $table): void {
            $table->json('data_categories')->nullable()->after('observation_summary');
        });
    }

    public function down(): void
    {
        Schema::table('dragon_agent_traces', function (Blueprint $table): void {
            $table->dropColumn('data_categories');
        });
        Schema::dropIfExists('dragon_knowledge_documents');
        Schema::dropIfExists('dragon_knowledge_sources');
    }
};
