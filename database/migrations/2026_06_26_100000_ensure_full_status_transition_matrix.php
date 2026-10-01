<?php

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ro_statuses') || ! Schema::hasTable('ro_status_transitions')) {
            return;
        }

        if (! RepairOrderStatusDefinition::query()->exists()) {
            return;
        }

        RepairOrderStatusCatalogDefaults::ensureFullTransitionMatrix(app(RepairOrderStatusCatalog::class));
    }

    public function down(): void
    {
        // Shop-specific transition permissions are operational data.
    }
};
