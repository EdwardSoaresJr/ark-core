<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scope_entry_concepts', function (Blueprint $table): void {
            $table->id();
            $table->string('canonical_summary');
            $table->string('scope_entry_kind', 32);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->unique(['canonical_summary', 'scope_entry_kind'], 'scope_concept_kind_summary_uq');
            $table->index(['scope_entry_kind', 'usage_count'], 'scope_concept_kind_usage_idx');
        });

        Schema::create('scope_entry_concept_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scope_entry_concept_id')->constrained('scope_entry_concepts')->cascadeOnDelete();
            $table->string('audience', 32)->default('customer');
            $table->string('observed_summary');
            $table->string('normalized_summary');
            $table->foreignId('repair_order_concern_id')->nullable()->constrained('repair_order_concerns')->nullOnDelete();
            $table->timestamps();

            $table->index('scope_entry_concept_id', 'scope_concept_obs_concept_idx');
            $table->unique(
                ['scope_entry_concept_id', 'normalized_summary', 'audience'],
                'scope_concept_obs_norm_aud_uq',
            );
            $table->index('normalized_summary', 'scope_concept_obs_norm_idx');
        });

        Schema::table('repair_order_concerns', function (Blueprint $table): void {
            $table->foreignId('scope_entry_concept_id')
                ->nullable()
                ->after('scope_entry_kind')
                ->constrained('scope_entry_concepts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scope_entry_concept_id');
        });

        Schema::dropIfExists('scope_entry_concept_observations');
        Schema::dropIfExists('scope_entry_concepts');
    }
};
