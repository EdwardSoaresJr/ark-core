<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->string('scope_entry_kind', 32)->default('customer_concern')->after('summary');
            $table->index(['repair_order_id', 'scope_entry_kind'], 'ro_concerns_ro_entry_kind_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->dropIndex('ro_concerns_ro_entry_kind_idx');
            $table->dropColumn('scope_entry_kind');
        });
    }
};
