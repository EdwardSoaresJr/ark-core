<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('concern_summary');
            $table->timestamp('closed_at')->nullable()->after('opened_at');

            $table->index(['status', 'closed_at'], 'ro_status_closed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropIndex('ro_status_closed_idx');
            $table->dropColumn(['opened_at', 'closed_at']);
        });
    }
};
