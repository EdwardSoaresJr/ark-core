<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shop_settings', 'document_typography')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->string('document_typography', 16)->default('standard');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('shop_settings', 'document_typography')) {
            Schema::table('shop_settings', function (Blueprint $table) {
                $table->dropColumn('document_typography');
            });
        }
    }
};
