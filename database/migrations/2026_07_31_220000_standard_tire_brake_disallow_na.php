<?php

use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Tires and brakes exist on every vehicle — remove N/A from Standard template points.
 * Runtime InspectionItem rows are unchanged; walk options read template allows_na.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('inspection_template_items', 'builder_meta')) {
            DefaultInspectionTemplateCatalog::rebuildStandardCornerInspectionV1();
        }

        DefaultInspectionTemplateCatalog::syncStandardAllowsNaFromDefinition();
        DefaultInspectionTemplateCatalog::disableNaOnLegacyTireBrakeCategories();
    }

    public function down(): void
    {
        // Intentionally empty — restoring N/A on tires/brakes is not a desired rollback.
    }
};
