<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shop_settings', 'installation_mail_mode')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->string('installation_mail_mode', 32)->default('disabled');
            });
        }

        if (Schema::hasColumn('shop_settings', 'installation_mail_enabled')) {
            DB::table('shop_settings')
                ->where('installation_mail_enabled', true)
                ->where(function ($query): void {
                    $query->whereNull('installation_mail_mode')
                        ->orWhere('installation_mail_mode', 'disabled');
                })
                ->update(['installation_mail_mode' => 'shop_settings']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shop_settings', 'installation_mail_mode')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->dropColumn('installation_mail_mode');
            });
        }
    }
};
