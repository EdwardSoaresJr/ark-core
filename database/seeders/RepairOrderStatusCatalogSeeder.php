<?php

namespace Database\Seeders;

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use Illuminate\Database\Seeder;

class RepairOrderStatusCatalogSeeder extends Seeder
{
    public function run(): void
    {
        RepairOrderStatusCatalogDefaults::sync(app(RepairOrderStatusCatalog::class));
    }
}
