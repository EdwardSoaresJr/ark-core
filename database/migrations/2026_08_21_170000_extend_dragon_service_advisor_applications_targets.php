<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dragon_service_advisor_applications', function (Blueprint $table): void {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['concern_id']);
            }
        });

        Schema::table('dragon_service_advisor_applications', function (Blueprint $table): void {
            $table->unsignedBigInteger('concern_id')->nullable()->change();

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('concern_id', 'dsa_apps_concern_fk')
                    ->references('id')
                    ->on('repair_order_concerns')
                    ->cascadeOnDelete();
            }

            $table->unsignedBigInteger('repair_order_line_id')->nullable()->after('concern_id');

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('repair_order_line_id', 'dsa_apps_line_fk')
                    ->references('id')
                    ->on('repair_order_lines')
                    ->cascadeOnDelete();

                $table->index(
                    ['repair_order_id', 'repair_order_line_id', 'field'],
                    'dsa_apps_ro_line_field_idx'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('dragon_service_advisor_applications', function (Blueprint $table): void {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropIndex('dsa_apps_ro_line_field_idx');
                $table->dropForeign('dsa_apps_line_fk');
            }
            $table->dropColumn('repair_order_line_id');

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign('dsa_apps_concern_fk');
            }
        });

        Schema::table('dragon_service_advisor_applications', function (Blueprint $table): void {
            $table->unsignedBigInteger('concern_id')->nullable(false)->change();
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('concern_id', 'dsa_apps_concern_fk')
                    ->references('id')
                    ->on('repair_order_concerns')
                    ->cascadeOnDelete();
            }
        });
    }
};
