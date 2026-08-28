<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telephony_extensions')) {
            return;
        }

        $shopId = DB::table('shop_settings')->value('id');

        if ($shopId === null) {
            return;
        }

        $this->syncRightDesk($shopId);
        $this->syncLeftDesk($shopId);
    }

    private function syncRightDesk(int $shopId): void
    {
        if (Schema::hasTable('workstations')) {
            DB::table('workstations')
                ->where('name', 'Left Station')
                ->orWhere('name', 'Front Desk')
                ->orWhere('name', 'Right Phone')
                ->orWhere('name', 'Front Desk Right')
                ->update([
                    'name' => 'Front Desk Right',
                    'location_label' => 'Front Desk Right',
                    'updated_at' => now(),
                ]);
        }

        $rightWorkstationId = DB::table('workstations')
            ->where('shop_settings_id', $shopId)
            ->where('name', 'Front Desk Right')
            ->value('id');

        $extensionIds = DB::table('telephony_extensions')
            ->where(function ($query) use ($rightWorkstationId): void {
                $query->whereIn('extension', ['101', 'desk1']);

                if ($rightWorkstationId !== null && Schema::hasColumn('telephony_extensions', 'workstation_id')) {
                    $query->orWhere('workstation_id', $rightWorkstationId);
                }
            })
            ->pluck('id');

        if ($extensionIds->isEmpty()) {
            return;
        }

        $updates = [
            'extension' => 'desk1',
            'display_name' => 'Front Desk Right',
            'updated_at' => now(),
        ];

        DB::table('telephony_extensions')
            ->whereIn('id', $extensionIds)
            ->update($updates);

        if (Schema::hasTable('communication_devices')) {
            DB::table('communication_devices')
                ->where('name', 'Right Phone')
                ->orWhere('name', 'Left Station')
                ->update([
                    'name' => 'Front Desk Right',
                    'provider_identifier' => 'desk1',
                    'updated_at' => now(),
                ]);
        }
    }

    private function syncLeftDesk(int $shopId): void
    {
        if (! Schema::hasTable('workstations')) {
            return;
        }

        $leftWorkstationId = DB::table('workstations')
            ->where('shop_settings_id', $shopId)
            ->where('name', 'Front Desk Left')
            ->value('id');

        if ($leftWorkstationId === null) {
            $leftWorkstationId = DB::table('workstations')->insertGetId([
                'shop_settings_id' => $shopId,
                'name' => 'Front Desk Left',
                'location_label' => 'Front Desk Left',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $desk2Exists = DB::table('telephony_extensions')
            ->where('extension', 'desk2')
            ->exists();

        if (! $desk2Exists) {
            $insert = [
                'extension' => 'desk2',
                'display_name' => 'Front Desk Left',
                'device_type' => 'desk_phone',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('telephony_extensions', 'workstation_id')) {
                $insert['workstation_id'] = $leftWorkstationId;
            }

            DB::table('telephony_extensions')->insert($insert);
        } else {
            $updates = [
                'display_name' => 'Front Desk Left',
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('telephony_extensions', 'workstation_id')) {
                $updates['workstation_id'] = $leftWorkstationId;
            }

            DB::table('telephony_extensions')
                ->where('extension', 'desk2')
                ->update($updates);
        }

        if (Schema::hasTable('communication_devices')) {
            DB::table('communication_devices')
                ->where('name', 'Left Phone')
                ->orWhere('name', 'Service Desk')
                ->update([
                    'name' => 'Front Desk Left',
                    'provider_identifier' => 'desk2',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Label sync is forward-only; endpoint rename migration handles telephony_endpoints rollback.
    }
};
