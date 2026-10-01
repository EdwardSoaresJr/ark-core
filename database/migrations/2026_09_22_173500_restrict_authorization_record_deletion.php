<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceConcernAndRepairOrderDeletes('restrictOnDelete');
    }

    public function down(): void
    {
        $this->replaceConcernAndRepairOrderDeletes('cascadeOnDelete');
    }

    private function replaceConcernAndRepairOrderDeletes(string $onDelete): void
    {
        foreach (['approved_work_scopes', 'authorization_exceptions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['repair_order_id']);
                $table->dropForeign(['repair_order_concern_id']);
            });

            Schema::table($tableName, function (Blueprint $table) use ($onDelete): void {
                $table->foreign('repair_order_id')
                    ->references('id')
                    ->on('repair_orders')
                    ->{$onDelete}();

                $table->foreign('repair_order_concern_id')
                    ->references('id')
                    ->on('repair_order_concerns')
                    ->{$onDelete}();
            });
        }
    }
};
