<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dual-write Platform connection columns alongside legacy cloud_* names.
 * Does not drop cloud_* — paired installations keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'platform_status')) {
                $table->string('platform_status', 32)->nullable()->after('cloud_pairing_expires_at');
            }
            if (! Schema::hasColumn('shop_settings', 'platform_base_url')) {
                $table->string('platform_base_url', 255)->nullable()->after('platform_status');
            }
            if (! Schema::hasColumn('shop_settings', 'platform_shop_public_id')) {
                $table->string('platform_shop_public_id', 36)->nullable()->after('platform_base_url');
            }
            if (! Schema::hasColumn('shop_settings', 'platform_credential')) {
                $table->text('platform_credential')->nullable()->after('platform_shop_public_id');
            }
            if (! Schema::hasColumn('shop_settings', 'platform_connected_at')) {
                $table->timestamp('platform_connected_at')->nullable()->after('platform_credential');
            }
            if (! Schema::hasColumn('shop_settings', 'platform_pairing_public_id')) {
                $table->string('platform_pairing_public_id', 36)->nullable()->after('platform_connected_at');
            }
            if (! Schema::hasColumn('shop_settings', 'platform_pairing_code')) {
                $table->string('platform_pairing_code', 16)->nullable()->after('platform_pairing_public_id');
            }
            if (! Schema::hasColumn('shop_settings', 'platform_pairing_expires_at')) {
                $table->timestamp('platform_pairing_expires_at')->nullable()->after('platform_pairing_code');
            }
        });

        DB::table('shop_settings')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $updates = [];
                $pairs = [
                    'platform_status' => 'cloud_status',
                    'platform_base_url' => 'cloud_base_url',
                    'platform_shop_public_id' => 'cloud_shop_public_id',
                    'platform_credential' => 'cloud_credential',
                    'platform_connected_at' => 'cloud_connected_at',
                    'platform_pairing_public_id' => 'cloud_pairing_public_id',
                    'platform_pairing_code' => 'cloud_pairing_code',
                    'platform_pairing_expires_at' => 'cloud_pairing_expires_at',
                ];

                foreach ($pairs as $platformCol => $cloudCol) {
                    $platformVal = $row->{$platformCol} ?? null;
                    $cloudVal = $row->{$cloudCol} ?? null;
                    if (blank($platformVal) && filled($cloudVal)) {
                        $updates[$platformCol] = $cloudVal;
                    }
                }

                if ($updates !== []) {
                    DB::table('shop_settings')->where('id', $row->id)->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $cols = [
                'platform_status',
                'platform_base_url',
                'platform_shop_public_id',
                'platform_credential',
                'platform_connected_at',
                'platform_pairing_public_id',
                'platform_pairing_code',
                'platform_pairing_expires_at',
            ];
            $drop = array_values(array_filter($cols, fn (string $c): bool => Schema::hasColumn('shop_settings', $c)));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
