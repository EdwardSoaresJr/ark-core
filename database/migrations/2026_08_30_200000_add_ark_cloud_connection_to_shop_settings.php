<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->string('cloud_status', 32)->nullable()->after('ark_mail_connected_at');
            $table->string('cloud_base_url', 255)->nullable()->after('cloud_status');
            $table->string('cloud_shop_public_id', 36)->nullable()->after('cloud_base_url');
            $table->text('cloud_credential')->nullable()->after('cloud_shop_public_id');
            $table->timestamp('cloud_connected_at')->nullable()->after('cloud_credential');
            $table->string('cloud_pairing_public_id', 36)->nullable()->after('cloud_connected_at');
            $table->string('cloud_pairing_code', 16)->nullable()->after('cloud_pairing_public_id');
            $table->timestamp('cloud_pairing_expires_at')->nullable()->after('cloud_pairing_code');
        });

        // Move Box↔Cloud connection fields out of Mail-only columns when present.
        DB::table('shop_settings')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $updates = [];

                if (filled($row->ark_mail_service_url ?? null) && blank($row->cloud_base_url ?? null)) {
                    $updates['cloud_base_url'] = $row->ark_mail_service_url;
                }
                if (filled($row->ark_mail_tenant_public_id ?? null) && blank($row->cloud_shop_public_id ?? null)) {
                    $updates['cloud_shop_public_id'] = $row->ark_mail_tenant_public_id;
                }
                if (filled($row->ark_mail_credential ?? null) && blank($row->cloud_credential ?? null)) {
                    $updates['cloud_credential'] = $row->ark_mail_credential;
                }
                if (filled($row->ark_mail_connected_at ?? null) && blank($row->cloud_connected_at ?? null)) {
                    $updates['cloud_connected_at'] = $row->ark_mail_connected_at;
                }

                $mailStatus = $row->ark_mail_status ?? null;
                if (blank($row->cloud_status ?? null) && filled($mailStatus)) {
                    $updates['cloud_status'] = match ($mailStatus) {
                        'connected' => 'connected',
                        'pairing' => 'pairing',
                        'suspended' => 'suspended',
                        default => $mailStatus,
                    };
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
            $table->dropColumn([
                'cloud_status',
                'cloud_base_url',
                'cloud_shop_public_id',
                'cloud_credential',
                'cloud_connected_at',
                'cloud_pairing_public_id',
                'cloud_pairing_code',
                'cloud_pairing_expires_at',
            ]);
        });
    }
};
