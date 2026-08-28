<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('kiosk_access_token', 64)->nullable()->unique()->after('photo_booth_enabled');
        });

        $events = \Illuminate\Support\Facades\DB::table('events')
            ->whereNull('kiosk_access_token')
            ->pluck('id');

        foreach ($events as $eventId) {
            \Illuminate\Support\Facades\DB::table('events')
                ->where('id', $eventId)
                ->update([
                    'kiosk_access_token' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('kiosk_access_token');
        });
    }
};
