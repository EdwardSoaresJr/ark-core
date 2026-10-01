<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_short_links', function (Blueprint $table): void {
            $table->foreignId('repair_order_id')
                ->nullable()
                ->after('code')
                ->constrained('repair_orders')
                ->cascadeOnDelete();
            $table->string('purpose', 16)->nullable()->after('repair_order_id');
            $table->index(['repair_order_id', 'purpose'], 'portal_slink_ro_purpose_idx');
        });
    }

    public function down(): void
    {
        Schema::table('portal_short_links', function (Blueprint $table): void {
            $table->dropIndex('portal_slink_ro_purpose_idx');
            $table->dropConstrainedForeignId('repair_order_id');
            $table->dropColumn('purpose');
        });
    }
};
