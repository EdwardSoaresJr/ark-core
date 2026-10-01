<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->string('collection_disposition', 32)->default('retail')->after('payment_status');
            $table->text('collection_disposition_reason')->nullable()->after('collection_disposition');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->dropColumn(['collection_disposition', 'collection_disposition_reason']);
        });
    }
};
