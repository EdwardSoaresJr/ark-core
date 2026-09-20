<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent repair for production deploys where 2026_06_25_100000 failed mid-flight
 * and left template tables without inspection_items columns (or without items table).
 */
return new class extends Migration
{
    private const TPL_CAT_TEMPLATE_FK = 'insp_tpl_cat_tpl_fk';

    private const TPL_ITEM_CATEGORY_FK = 'insp_tpl_item_cat_fk';

    private const INSP_ITEM_TEMPLATE_FK = 'insp_item_tpl_item_fk';

    public function up(): void
    {
        if (Schema::hasColumn('inspection_items', 'inspection_template_item_id')) {
            return;
        }

        if (! Schema::hasTable('inspection_templates')) {
            Schema::create('inspection_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->boolean('enabled')->default(true);
                $table->boolean('is_default')->default(false);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inspection_template_categories')) {
            Schema::create('inspection_template_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inspection_template_id');
                $table->string('name', 120);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->foreign('inspection_template_id', self::TPL_CAT_TEMPLATE_FK)
                    ->references('id')
                    ->on('inspection_templates')
                    ->cascadeOnDelete();

                $table->index(['inspection_template_id', 'position'], 'insp_tpl_cat_tpl_pos_idx');
            });
        }

        if (! Schema::hasTable('inspection_template_items')) {
            Schema::create('inspection_template_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inspection_template_category_id');
                $table->string('label', 191);
                $table->unsignedInteger('position')->default(0);
                $table->boolean('requires_photo')->default(false);
                $table->string('measurement_name', 120)->nullable();
                $table->string('measurement_unit', 32)->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->foreign('inspection_template_category_id', self::TPL_ITEM_CATEGORY_FK)
                    ->references('id')
                    ->on('inspection_template_categories')
                    ->cascadeOnDelete();

                $table->index(['inspection_template_category_id', 'position'], 'insp_tpl_item_cat_pos_idx');
            });
        }

        if (! Schema::hasColumn('inspection_items', 'checklist_category_name')) {
            Schema::table('inspection_items', function (Blueprint $table) {
                $table->string('checklist_category_name', 120)->nullable()->after('category');
            });
        }

        if (! Schema::hasColumn('inspection_items', 'inspection_template_item_id')) {
            Schema::table('inspection_items', function (Blueprint $table) {
                $table->unsignedBigInteger('inspection_template_item_id')->nullable()->after('position');

                $table->foreign('inspection_template_item_id', self::INSP_ITEM_TEMPLATE_FK)
                    ->references('id')
                    ->on('inspection_template_items')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Repair migration — down is handled by 2026_06_25_100000.
    }
};
