<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wrong-template correction: keep prior points as superseded history on the same Inspection.
 * One Inspection per RO remains — no destroy path for captured evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inspection_items') && ! Schema::hasColumn('inspection_items', 'superseded_at')) {
            Schema::table('inspection_items', function (Blueprint $table): void {
                $table->timestamp('superseded_at')->nullable()->after('inspection_template_item_id');
                $table->index(['inspection_id', 'superseded_at'], 'insp_items_active_idx');
            });
        }

        if (Schema::hasTable('inspections')) {
            Schema::table('inspections', function (Blueprint $table): void {
                if (! Schema::hasColumn('inspections', 'previous_inspection_template_id')) {
                    $table->unsignedBigInteger('previous_inspection_template_id')->nullable()->after('inspection_template_id');
                }
                if (! Schema::hasColumn('inspections', 'template_correction_reason')) {
                    $table->string('template_correction_reason', 255)->nullable()->after('previous_inspection_template_id');
                }
                if (! Schema::hasColumn('inspections', 'template_corrected_at')) {
                    $table->timestamp('template_corrected_at')->nullable()->after('template_correction_reason');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inspection_items') && Schema::hasColumn('inspection_items', 'superseded_at')) {
            Schema::table('inspection_items', function (Blueprint $table): void {
                $table->dropIndex('insp_items_active_idx');
                $table->dropColumn('superseded_at');
            });
        }

        if (Schema::hasTable('inspections')) {
            Schema::table('inspections', function (Blueprint $table): void {
                if (Schema::hasColumn('inspections', 'template_corrected_at')) {
                    $table->dropColumn('template_corrected_at');
                }
                if (Schema::hasColumn('inspections', 'template_correction_reason')) {
                    $table->dropColumn('template_correction_reason');
                }
                if (Schema::hasColumn('inspections', 'previous_inspection_template_id')) {
                    $table->dropColumn('previous_inspection_template_id');
                }
            });
        }
    }
};
