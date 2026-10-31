<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approved_work_scopes', function (Blueprint $table): void {
            $table->json('approved_lines')->nullable()->after('line_ids');
        });
    }

    public function down(): void
    {
        Schema::table('approved_work_scopes', function (Blueprint $table): void {
            $table->dropColumn('approved_lines');
        });
    }
};
