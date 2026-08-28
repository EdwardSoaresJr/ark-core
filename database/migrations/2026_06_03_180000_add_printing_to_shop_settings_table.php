<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->boolean('qz_printing_enabled')->default(true)->after('default_estimate_state');
            $table->string('qz_printing_key_tag_printer', 255)->nullable()->after('qz_printing_enabled');
            $table->string('qz_printing_oil_sticker_printer', 255)->nullable()->after('qz_printing_key_tag_printer');
            $table->decimal('qz_key_tag_label_width_mm', 6, 2)->nullable()->after('qz_printing_oil_sticker_printer');
            $table->decimal('qz_key_tag_label_height_mm', 6, 2)->nullable()->after('qz_key_tag_label_width_mm');
            $table->string('qz_key_tag_vin_display', 16)->default('last6')->after('qz_key_tag_label_height_mm');
            $table->string('qz_key_tag_media_type', 32)->default('mono')->after('qz_key_tag_vin_display');
            $table->string('qz_key_tag_orientation', 16)->default('auto')->after('qz_key_tag_media_type');
            $table->unsignedSmallInteger('qz_raster_dpi')->nullable()->after('qz_key_tag_orientation');
            $table->unsignedSmallInteger('oil_change_sticker_next_due_months')->default(6)->after('qz_raster_dpi');
            $table->unsignedInteger('oil_change_interval_miles')->default(5000)->after('oil_change_sticker_next_due_months');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn([
                'qz_printing_enabled',
                'qz_printing_key_tag_printer',
                'qz_printing_oil_sticker_printer',
                'qz_key_tag_label_width_mm',
                'qz_key_tag_label_height_mm',
                'qz_key_tag_vin_display',
                'qz_key_tag_media_type',
                'qz_key_tag_orientation',
                'qz_raster_dpi',
                'oil_change_sticker_next_due_months',
                'oil_change_interval_miles',
            ]);
        });
    }
};
