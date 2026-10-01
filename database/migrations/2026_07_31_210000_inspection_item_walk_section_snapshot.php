<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime technician-walk placement snapshot on InspectionItem.
 *
 * Builder placement is copied at apply time. Null = legacy (category-based walk).
 * No backfill of historical rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inspection_items')
            && ! Schema::hasColumn('inspection_items', 'walk_section')) {
            Schema::table('inspection_items', function (Blueprint $table): void {
                $table->string('walk_section', 64)->nullable()->after('checklist_category_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inspection_items')
            && Schema::hasColumn('inspection_items', 'walk_section')) {
            Schema::table('inspection_items', function (Blueprint $table): void {
                $table->dropColumn('walk_section');
            });
        }
    }
};
