<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dragon_agent_conversations', function (Blueprint $table): void {
            $table->json('pending_memory')->nullable();
        });

        Schema::table('dragon_agent_memories', function (Blueprint $table): void {
            $table->string('scope_type', 16)->default('company');
            $table->foreignId('workstation_id')->nullable()->constrained('workstations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 32)->nullable();
            $table->unsignedBigInteger('supersedes_id')->nullable();
            $table->index(['scope_type', 'workstation_id', 'superseded_at'], 'dragon_mem_scope_ws');
            $table->index(['scope_type', 'user_id', 'superseded_at'], 'dragon_mem_scope_user');
        });

        DB::table('dragon_agent_memories')->whereNull('scope_type')->orWhere('scope_type', '')->update([
            'scope_type' => 'company',
            'category' => 'standard',
        ]);

        $this->supersedeDuplicateAlternatorStandards();
        $this->cleanLandonPersonalFacts();
    }

    public function down(): void
    {
        Schema::table('dragon_agent_memories', function (Blueprint $table): void {
            $table->dropIndex('dragon_mem_scope_ws');
            $table->dropIndex('dragon_mem_scope_user');
            $table->dropConstrainedForeignId('workstation_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['scope_type', 'category', 'supersedes_id']);
        });

        Schema::table('dragon_agent_conversations', function (Blueprint $table): void {
            $table->dropColumn('pending_memory');
        });
    }

    private function supersedeDuplicateAlternatorStandards(): void
    {
        $rows = DB::table('dragon_agent_memories')
            ->whereNull('superseded_at')
            ->where(function ($query): void {
                $query->where('fact_value', 'like', '%alternator%')
                    ->where('fact_value', 'like', '%battery voltage%');
            })
            ->orderBy('id')
            ->get();

        if ($rows->count() < 2) {
            return;
        }

        $keepKey = 'arkai:e642d05d-b10e-48e6-b1b1-77d77052d3d0';
        $keep = $rows->firstWhere('fact_key', $keepKey) ?? $rows->first();

        foreach ($rows as $row) {
            if ((int) $row->id === (int) $keep->id) {
                continue;
            }
            DB::table('dragon_agent_memories')->where('id', $row->id)->update([
                'superseded_at' => now(),
                'provenance' => trim((string) $row->provenance.'|cleanup:duplicate-alternator'),
            ]);
        }
    }

    private function cleanLandonPersonalFacts(): void
    {
        $landon = User::query()->where('name', 'like', '%Landon%')->first();
        $rows = DB::table('dragon_agent_memories')
            ->whereNull('superseded_at')
            ->where('fact_value', 'like', '%Landon%')
            ->get();

        foreach ($rows as $row) {
            $value = (string) $row->fact_value;
            $personal = (bool) preg_match('/\b(birthday|born|years old|\bage\s+\d+)/i', $value);
            if ($landon !== null) {
                DB::table('dragon_agent_memories')->where('id', $row->id)->update([
                    'superseded_at' => now(),
                    'provenance' => trim((string) $row->provenance.'|cleanup:staff-role-is-ark-authority'),
                ]);
                continue;
            }
            if ($personal) {
                DB::table('dragon_agent_memories')->where('id', $row->id)->update([
                    'fact_value' => 'Landon is a technician.',
                    'category' => 'standard',
                    'provenance' => trim((string) $row->provenance.'|cleanup:removed-personal-age'),
                ]);
            }
        }
    }
};
