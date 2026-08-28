<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scope_entry_concept_observations')) {
            return;
        }

        if (! Schema::hasColumn('scope_entry_concept_observations', 'audience')) {
            Schema::table('scope_entry_concept_observations', function (Blueprint $table): void {
                $table->string('audience', 32)->default('customer')->after('scope_entry_concept_id');
            });
        }

        if ($this->indexExists('scope_entry_concept_observations', 'scope_concept_obs_norm_aud_uq')) {
            return;
        }

        if (! $this->indexExists('scope_entry_concept_observations', 'scope_concept_obs_concept_idx')) {
            Schema::table('scope_entry_concept_observations', function (Blueprint $table): void {
                $table->index('scope_entry_concept_id', 'scope_concept_obs_concept_idx');
            });
        }

        if ($this->indexExists('scope_entry_concept_observations', 'scope_concept_obs_norm_uq')) {
            Schema::table('scope_entry_concept_observations', function (Blueprint $table): void {
                $table->dropUnique('scope_concept_obs_norm_uq');
            });
        }

        if (! $this->indexExists('scope_entry_concept_observations', 'scope_concept_obs_norm_aud_uq')) {
            Schema::table('scope_entry_concept_observations', function (Blueprint $table): void {
                $table->unique(
                    ['scope_entry_concept_id', 'normalized_summary', 'audience'],
                    'scope_concept_obs_norm_aud_uq',
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('scope_entry_concept_observations')) {
            return;
        }

        if ($this->indexExists('scope_entry_concept_observations', 'scope_concept_obs_norm_aud_uq')) {
            Schema::table('scope_entry_concept_observations', function (Blueprint $table): void {
                $table->dropUnique('scope_concept_obs_norm_aud_uq');
            });
        }

        if (! $this->indexExists('scope_entry_concept_observations', 'scope_concept_obs_norm_uq')) {
            Schema::table('scope_entry_concept_observations', function (Blueprint $table): void {
                $table->unique(['scope_entry_concept_id', 'normalized_summary'], 'scope_concept_obs_norm_uq');
            });
        }

        if ($this->indexExists('scope_entry_concept_observations', 'scope_concept_obs_concept_idx')) {
            Schema::table('scope_entry_concept_observations', function (Blueprint $table): void {
                $table->dropIndex('scope_concept_obs_concept_idx');
            });
        }

        if (Schema::hasColumn('scope_entry_concept_observations', 'audience')) {
            Schema::table('scope_entry_concept_observations', function (Blueprint $table): void {
                $table->dropColumn('audience');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return $rows !== [];
    }
};
