<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historical one-shot: reconcile two front-desk VVX rows when matching MACs exist.
 *
 * Public staging ships synthetic placeholder MACs only. On a fresh Demo Auto
 * install these constants match nothing, so this migration is a no-op.
 * Original proving-ground hardware inventory was removed from the public tree.
 */
return new class extends Migration
{
    private const RIGHT_MAC = '000000000001';

    private const LEFT_MAC = '000000000002';

    public function up(): void
    {
        if (! Schema::hasTable('communication_devices') || ! Schema::hasTable('telephony_extensions')) {
            return;
        }

        $shopId = DB::table('shop_settings')->value('id');

        if ($shopId === null) {
            return;
        }

        $rightWorkstationId = $this->workstationId($shopId, 'Front Desk Right');
        $leftWorkstationId = $this->workstationId($shopId, 'Front Desk Left');

        if ($rightWorkstationId === null || $leftWorkstationId === null) {
            return;
        }

        $this->syncExtension('desk1', 'Front Desk Right', $rightWorkstationId);
        $this->syncExtension('desk2', 'Front Desk Left', $leftWorkstationId);

        $rightDeviceId = $this->ensureDevice(
            shopId: $shopId,
            mac: self::RIGHT_MAC,
            workstationId: $rightWorkstationId,
            name: 'Front Desk Right',
            providerIdentifier: 'desk1',
        );

        $leftDeviceId = $this->ensureDevice(
            shopId: $shopId,
            mac: self::LEFT_MAC,
            workstationId: $leftWorkstationId,
            name: 'Front Desk Left',
            providerIdentifier: 'desk2',
        );

        if ($rightDeviceId !== null) {
            $this->linkExtensionToDevice('desk1', $rightDeviceId);
        }

        if ($leftDeviceId !== null) {
            $this->linkExtensionToDevice('desk2', $leftDeviceId);
        }
    }

    private function workstationId(int $shopId, string $name): ?int
    {
        if (! Schema::hasTable('workstations')) {
            return null;
        }

        $id = DB::table('workstations')
            ->where('shop_settings_id', $shopId)
            ->where('name', $name)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function syncExtension(string $extension, string $displayName, int $workstationId): void
    {
        $existing = DB::table('telephony_extensions')
            ->where('extension', $extension)
            ->first();

        $payload = [
            'display_name' => $displayName,
            'device_type' => 'desk_phone',
            'enabled' => true,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('telephony_extensions', 'workstation_id')) {
            $payload['workstation_id'] = $workstationId;
        }

        if ($existing !== null) {
            DB::table('telephony_extensions')
                ->where('id', $existing->id)
                ->update($payload);

            return;
        }

        $payload['extension'] = $extension;
        $payload['created_at'] = now();

        DB::table('telephony_extensions')->insert($payload);
    }

    private function ensureDevice(
        int $shopId,
        string $mac,
        int $workstationId,
        string $name,
        string $providerIdentifier,
    ): ?int {
        if (! Schema::hasColumn('communication_devices', 'mac_address')) {
            return null;
        }

        $existing = DB::table('communication_devices')
            ->where('mac_address', $mac)
            ->first();

        $payload = [
            'shop_settings_id' => $shopId,
            'name' => $name,
            'provider' => 'shop_phone',
            'provider_identifier' => $providerIdentifier,
            'status' => 'connected',
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('communication_devices', 'workstation_id')) {
            $payload['workstation_id'] = $workstationId;
        }

        if (Schema::hasColumn('communication_devices', 'model')) {
            $payload['model'] = 'VVX350';
        }

        if ($existing !== null) {
            DB::table('communication_devices')
                ->where('id', $existing->id)
                ->update($payload);

            return (int) $existing->id;
        }

        $peerMac = $mac === self::RIGHT_MAC ? self::LEFT_MAC : self::RIGHT_MAC;
        $peerExists = DB::table('communication_devices')
            ->where('mac_address', $peerMac)
            ->exists();

        if (! $peerExists) {
            return null;
        }

        $payload['mac_address'] = $mac;
        $payload['created_at'] = now();

        if (Schema::hasColumn('communication_devices', 'capabilities')) {
            $payload['capabilities'] = json_encode(['voice', 'microbrowser']);
        }

        return (int) DB::table('communication_devices')->insertGetId($payload);
    }

    private function linkExtensionToDevice(string $extension, int $deviceId): void
    {
        if (! Schema::hasColumn('telephony_extensions', 'communication_device_id')) {
            return;
        }

        DB::table('telephony_extensions')
            ->where('extension', $extension)
            ->update([
                'communication_device_id' => $deviceId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Forward-only inventory reconciliation.
    }
};
