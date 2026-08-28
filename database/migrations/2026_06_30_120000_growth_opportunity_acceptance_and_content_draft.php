<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_opportunities', function (Blueprint $table): void {
            $table->json('acceptance_criteria')->nullable()->after('evidence');
            $table->json('content_draft')->nullable()->after('acceptance_criteria');
        });
    }

    public function down(): void
    {
        Schema::table('growth_opportunities', function (Blueprint $table): void {
            $table->dropColumn(['acceptance_criteria', 'content_draft']);
        });
    }
};
