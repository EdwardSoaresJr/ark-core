<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('appointment_requests', 'selections')) {
            Schema::table('appointment_requests', function (Blueprint $table): void {
                $table->json('selections')->nullable()->after('answers');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('appointment_requests', 'selections')) {
            Schema::table('appointment_requests', function (Blueprint $table): void {
                $table->dropColumn('selections');
            });
        }
    }
};
