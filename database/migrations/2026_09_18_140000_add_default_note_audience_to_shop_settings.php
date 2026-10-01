<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->boolean('default_notes_visible_to_advisor')->default(true)->after('default_notes_private');
            $table->boolean('default_notes_visible_to_technician')->default(false)->after('default_notes_visible_to_advisor');
            $table->boolean('default_notes_visible_to_customer')->default(false)->after('default_notes_visible_to_technician');
        });

        DB::table('shop_settings')->update([
            'default_notes_visible_to_advisor' => true,
            'default_notes_visible_to_technician' => false,
            'default_notes_visible_to_customer' => DB::raw('CASE WHEN default_notes_private = 1 THEN 0 ELSE 1 END'),
        ]);
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn([
                'default_notes_visible_to_advisor',
                'default_notes_visible_to_technician',
                'default_notes_visible_to_customer',
            ]);
        });
    }
};
