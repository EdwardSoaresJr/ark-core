<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inspection_templates')) {
            Schema::table('inspection_templates', function (Blueprint $table): void {
                if (! Schema::hasColumn('inspection_templates', 'slug')) {
                    $table->string('slug', 64)->nullable()->after('name');
                }
                if (! Schema::hasColumn('inspection_templates', 'archived_at')) {
                    $table->timestamp('archived_at')->nullable()->after('position');
                }
            });

            if (Schema::hasColumn('inspection_templates', 'slug')) {
                try {
                    Schema::table('inspection_templates', function (Blueprint $table): void {
                        $table->unique('slug', 'insp_tpl_slug_unique');
                    });
                } catch (\Throwable) {
                    // Index may already exist on re-run / SQLite quirks.
                }
            }
        }

        if (Schema::hasTable('inspection_template_items')) {
            Schema::table('inspection_template_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('inspection_template_items', 'measurement_slots')) {
                    $table->json('measurement_slots')->nullable()->after('measurement_unit');
                }
                if (! Schema::hasColumn('inspection_template_items', 'point_key')) {
                    $table->string('point_key', 64)->nullable()->after('label');
                }
                if (! Schema::hasColumn('inspection_template_items', 'allows_na')) {
                    $table->boolean('allows_na')->default(true)->after('enabled');
                }
                if (! Schema::hasColumn('inspection_template_items', 'requires_scan_evidence')) {
                    $table->boolean('requires_scan_evidence')->default(false)->after('requires_photo');
                }
                if (! Schema::hasColumn('inspection_template_items', 'gate_group')) {
                    $table->string('gate_group', 64)->nullable()->after('point_key');
                }
                if (! Schema::hasColumn('inspection_template_items', 'axle_role')) {
                    $table->string('axle_role', 32)->nullable()->after('gate_group');
                }
            });
        }

        if (Schema::hasTable('inspections')) {
            Schema::table('inspections', function (Blueprint $table): void {
                if (! Schema::hasColumn('inspections', 'inspection_template_id')) {
                    $table->unsignedBigInteger('inspection_template_id')->nullable()->after('repair_order_id');
                    $table->foreign('inspection_template_id', 'insp_template_fk')
                        ->references('id')
                        ->on('inspection_templates')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('inspections', 'rear_axle_brake_type')) {
                    $table->string('rear_axle_brake_type', 16)->nullable()->after('notes');
                }
            });
        }

        if (Schema::hasTable('repair_orders') && ! Schema::hasColumn('repair_orders', 'required_inspection_template_id')) {
            Schema::table('repair_orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('required_inspection_template_id')->nullable()->after('assigned_technician_id');
                $table->foreign('required_inspection_template_id', 'ro_req_insp_tpl_fk')
                    ->references('id')
                    ->on('inspection_templates')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('shop_settings') && ! Schema::hasColumn('shop_settings', 'inspection_comparison')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->json('inspection_comparison')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_orders') && Schema::hasColumn('repair_orders', 'required_inspection_template_id')) {
            Schema::table('repair_orders', function (Blueprint $table): void {
                $table->dropForeign('ro_req_insp_tpl_fk');
                $table->dropColumn('required_inspection_template_id');
            });
        }

        if (Schema::hasTable('inspections')) {
            Schema::table('inspections', function (Blueprint $table): void {
                if (Schema::hasColumn('inspections', 'inspection_template_id')) {
                    $table->dropForeign('insp_template_fk');
                    $table->dropColumn('inspection_template_id');
                }
                if (Schema::hasColumn('inspections', 'rear_axle_brake_type')) {
                    $table->dropColumn('rear_axle_brake_type');
                }
            });
        }

        if (Schema::hasTable('inspection_template_items')) {
            Schema::table('inspection_template_items', function (Blueprint $table): void {
                foreach (['measurement_slots', 'point_key', 'allows_na', 'requires_scan_evidence', 'gate_group', 'axle_role'] as $column) {
                    if (Schema::hasColumn('inspection_template_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('inspection_templates')) {
            Schema::table('inspection_templates', function (Blueprint $table): void {
                if (Schema::hasColumn('inspection_templates', 'slug')) {
                    $table->dropUnique('insp_tpl_slug_unique');
                    $table->dropColumn('slug');
                }
                if (Schema::hasColumn('inspection_templates', 'archived_at')) {
                    $table->dropColumn('archived_at');
                }
            });
        }

        if (Schema::hasTable('shop_settings') && Schema::hasColumn('shop_settings', 'inspection_comparison')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->dropColumn('inspection_comparison');
            });
        }
    }
};
