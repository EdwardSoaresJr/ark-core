<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rte_lab')) {
            return;
        }

        if (! Schema::hasColumn('rte_lab', 'vehicle_segment')) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE rte_lab ADD COLUMN vehicle_segment VARCHAR(4) AS (SUBSTRING(lab_id, 5, 4)) STORED');
            } else {
                Schema::table('rte_lab', function (Blueprint $table): void {
                    $table->string('vehicle_segment', 4)->nullable()->after('lab_id');
                });

                DB::table('rte_lab')
                    ->select(['lab_id'])
                    ->orderBy('lab_id')
                    ->lazy(500)
                    ->each(function ($row): void {
                        $segment = strlen((string) $row->lab_id) >= 8
                            ? strtoupper(substr((string) $row->lab_id, 4, 4))
                            : null;

                        DB::table('rte_lab')
                            ->where('lab_id', $row->lab_id)
                            ->update(['vehicle_segment' => $segment]);
                    });
            }
        }

        Schema::table('rte_lab', function (Blueprint $table): void {
            if (! Schema::hasIndex('rte_lab', 'rte_lab_vehicle_segment_idx')) {
                $table->index('vehicle_segment', 'rte_lab_vehicle_segment_idx');
            }

            if (! Schema::hasIndex('rte_lab', 'rte_lab_model2_idx')) {
                $table->index('model2', 'rte_lab_model2_idx');
            }

            if (! Schema::hasIndex('rte_lab', 'rte_lab_model3_idx')) {
                $table->index('model3', 'rte_lab_model3_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rte_lab')) {
            return;
        }

        Schema::table('rte_lab', function (Blueprint $table): void {
            if (Schema::hasIndex('rte_lab', 'rte_lab_vehicle_segment_idx')) {
                $table->dropIndex('rte_lab_vehicle_segment_idx');
            }

            if (Schema::hasIndex('rte_lab', 'rte_lab_model2_idx')) {
                $table->dropIndex('rte_lab_model2_idx');
            }

            if (Schema::hasIndex('rte_lab', 'rte_lab_model3_idx')) {
                $table->dropIndex('rte_lab_model3_idx');
            }
        });

        if (Schema::hasColumn('rte_lab', 'vehicle_segment')) {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE rte_lab DROP COLUMN vehicle_segment');
            } else {
                Schema::table('rte_lab', function (Blueprint $table): void {
                    $table->dropColumn('vehicle_segment');
                });
            }
        }
    }
};
