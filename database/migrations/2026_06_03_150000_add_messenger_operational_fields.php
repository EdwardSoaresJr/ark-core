<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('messenger_psid', 64)->nullable()->after('phone');
            $table->index('messenger_psid', 'customers_messenger_psid_idx');
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->text('messenger_app_secret')->nullable()->after('communications_channels');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('messenger_app_secret');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_messenger_psid_idx');
            $table->dropColumn('messenger_psid');
        });
    }
};
