<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ArkAuthorizationSeeder::class);
        $this->call(RepairOrderStatusCatalogSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(ShopSettingsSeeder::class);
        $this->call(CommunicationDeviceModelSeeder::class);
        $this->call(TelephonyEndpointSeeder::class);
        $this->call(InternalChannelSeeder::class);
        $this->call(DemoWorkflowSeeder::class);
        $this->call(BulkOperationalDemoSeeder::class);
    }
}
