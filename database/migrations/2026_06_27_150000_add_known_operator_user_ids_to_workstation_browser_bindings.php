<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workstation_browser_bindings', function (Blueprint $table): void {
            if (! Schema::hasColumn('workstation_browser_bindings', 'known_operator_user_ids')) {
                $table->json('known_operator_user_ids')->nullable()->after('last_seen_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workstation_browser_bindings', function (Blueprint $table): void {
            if (Schema::hasColumn('workstation_browser_bindings', 'known_operator_user_ids')) {
                $table->dropColumn('known_operator_user_ids');
            }
        });
    }
};
