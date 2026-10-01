<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_sessions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('identity_confidence_score')->default(25)->after('city');
            $table->string('identity_confidence_reason')->nullable()->after('identity_confidence_score');
            $table->json('identity_confidence_evidence')->nullable()->after('identity_confidence_reason');
        });
    }

    public function down(): void
    {
        Schema::table('growth_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'identity_confidence_score',
                'identity_confidence_reason',
                'identity_confidence_evidence',
            ]);
        });
    }
};
