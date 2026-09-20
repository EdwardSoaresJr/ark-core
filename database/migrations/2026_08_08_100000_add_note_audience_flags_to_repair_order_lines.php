<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->boolean('visible_to_advisor')->default(false)->after('is_private');
            $table->boolean('visible_to_technician')->default(false)->after('visible_to_advisor');
            $table->boolean('visible_to_customer')->default(false)->after('visible_to_technician');
        });

        // Preserve prior semantics: private = staff (advisor + tech), not customer.
        // Customer-visible notes stay on tech sheet as they did before.
        DB::table('repair_order_lines')
            ->where('type', 'note')
            ->where('is_private', true)
            ->update([
                'visible_to_advisor' => true,
                'visible_to_technician' => true,
                'visible_to_customer' => false,
            ]);

        DB::table('repair_order_lines')
            ->where('type', 'note')
            ->where('is_private', false)
            ->update([
                'visible_to_advisor' => true,
                'visible_to_technician' => true,
                'visible_to_customer' => true,
            ]);

        DB::table('repair_order_lines')
            ->where('type', '!=', 'note')
            ->update([
                'visible_to_advisor' => false,
                'visible_to_technician' => false,
                'visible_to_customer' => false,
            ]);
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropColumn([
                'visible_to_advisor',
                'visible_to_technician',
                'visible_to_customer',
            ]);
        });
    }
};
