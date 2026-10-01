<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telephony_endpoints', function (Blueprint $table) {
            $table->unsignedSmallInteger('ring_delay_seconds')->default(0)->after('ring_schedule');
        });
    }

    public function down(): void
    {
        Schema::table('telephony_endpoints', function (Blueprint $table) {
            $table->dropColumn('ring_delay_seconds');
        });
    }
};
