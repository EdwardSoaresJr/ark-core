<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_access_tokens', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->after('token');
        });

        DB::table('estimate_access_tokens')
            ->whereNotNull('token')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $row): void {
                DB::table('estimate_access_tokens')
                    ->where('id', $row->id)
                    ->update(['token_hash' => hash('sha256', (string) $row->token)]);
            });

        Schema::table('estimate_access_tokens', function (Blueprint $table) {
            $table->dropUnique('est_access_token_unique');
            $table->dropColumn('token');
            $table->unique('token_hash', 'est_access_token_hash_unique');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE estimate_access_tokens MODIFY token_hash CHAR(64) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('estimate_access_tokens', function (Blueprint $table) {
            $table->dropUnique('est_access_token_hash_unique');
            $table->string('token', 64)->nullable()->after('repair_order_id');
        });

        Schema::table('estimate_access_tokens', function (Blueprint $table) {
            $table->dropColumn('token_hash');
            $table->unique('token', 'est_access_token_unique');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE estimate_access_tokens MODIFY token CHAR(64) NOT NULL');
        }
    }
};
