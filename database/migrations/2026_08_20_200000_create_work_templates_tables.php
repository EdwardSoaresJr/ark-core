<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rapid Work Templates — disposable authors of Repair Actions + lines.
 * Shop-wide (single-tenant) like inspection_templates. Not a Service Catalog.
 */
return new class extends Migration
{
    private const LINE_TEMPLATE_FK = 'work_tpl_line_tpl_fk';

    private const WG_TEMPLATE_FK = 'ro_wg_created_from_tpl_fk';

    public function up(): void
    {
        if (! Schema::hasTable('work_templates')) {
            Schema::create('work_templates', function (Blueprint $table) {
                $table->id();
                $table->string('title', 191);
                $table->string('description', 1000)->nullable();
                $table->text('internal_note')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamp('retired_at')->nullable();
                $table->timestamps();

                $table->index(['retired_at', 'position'], 'work_tpl_active_pos_idx');
            });
        }

        if (! Schema::hasTable('work_template_lines')) {
            Schema::create('work_template_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_template_id');
                $table->string('type', 16);
                $table->string('description', 2000);
                $table->decimal('quantity', 10, 2)->default(1);
                $table->unsignedInteger('unit_price_cents')->nullable();
                $table->unsignedInteger('part_cost_cents')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->foreign('work_template_id', self::LINE_TEMPLATE_FK)
                    ->references('id')
                    ->on('work_templates')
                    ->cascadeOnDelete();

                $table->index(['work_template_id', 'position'], 'work_tpl_line_pos_idx');
            });
        }

        if (Schema::hasTable('repair_order_work_groups')
            && ! Schema::hasColumn('repair_order_work_groups', 'created_from_template_id')) {
            Schema::table('repair_order_work_groups', function (Blueprint $table) {
                $table->unsignedBigInteger('created_from_template_id')->nullable()->after('latest_update');

                $table->foreign('created_from_template_id', self::WG_TEMPLATE_FK)
                    ->references('id')
                    ->on('work_templates')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_order_work_groups')
            && Schema::hasColumn('repair_order_work_groups', 'created_from_template_id')) {
            Schema::table('repair_order_work_groups', function (Blueprint $table) {
                $table->dropForeign(self::WG_TEMPLATE_FK);
                $table->dropColumn('created_from_template_id');
            });
        }

        Schema::dropIfExists('work_template_lines');
        Schema::dropIfExists('work_templates');
    }
};
