<?php

use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inspection_template_items')
            && ! Schema::hasColumn('inspection_template_items', 'builder_meta')) {
            Schema::table('inspection_template_items', function (Blueprint $table): void {
                $table->json('builder_meta')->nullable()->after('measurement_slots');
            });
        }

        if (Schema::hasTable('inspection_items')
            && ! Schema::hasColumn('inspection_items', 'selected_observations')) {
            Schema::table('inspection_items', function (Blueprint $table): void {
                $table->json('selected_observations')->nullable()->after('notes');
            });
        }

        if (Schema::hasTable('inspection_templates')) {
            DefaultInspectionTemplateCatalog::rebuildStandardCornerInspectionV1();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inspection_template_items')
            && Schema::hasColumn('inspection_template_items', 'builder_meta')) {
            Schema::table('inspection_template_items', function (Blueprint $table): void {
                $table->dropColumn('builder_meta');
            });
        }

        if (Schema::hasTable('inspection_items')
            && Schema::hasColumn('inspection_items', 'selected_observations')) {
            Schema::table('inspection_items', function (Blueprint $table): void {
                $table->dropColumn('selected_observations');
            });
        }
    }
};
