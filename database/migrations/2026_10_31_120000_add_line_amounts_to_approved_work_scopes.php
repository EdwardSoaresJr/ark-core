<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing scopes stay null: their approvals recorded no per-line amounts.
        Schema::table('approved_work_scopes', function (Blueprint $table) {
            $table->json('line_amounts')->nullable()->after('line_ids');
        });
    }

    public function down(): void
    {
        Schema::table('approved_work_scopes', function (Blueprint $table) {
            $table->dropColumn('line_amounts');
        });
    }
};
