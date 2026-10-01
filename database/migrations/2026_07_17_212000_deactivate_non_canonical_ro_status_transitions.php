<?php

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        RepairOrderStatusCatalogDefaults::deactivateNonCanonicalTransitions(
            app(RepairOrderStatusCatalog::class),
        );
    }

    public function down(): void
    {
        // Non-canonical rows remain; shops may re-enable via Settings → Workflow.
    }
};
