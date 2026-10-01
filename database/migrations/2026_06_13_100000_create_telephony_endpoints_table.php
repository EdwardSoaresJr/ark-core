<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('type', 16);
            $table->string('destination', 255);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['enabled', 'position'], 'tel_endpoints_ring_idx');
        });

        $forwardTo = DB::table('shop_settings')->value('telephony_forward_to');

        if (is_string($forwardTo) && trim($forwardTo) !== '') {
            DB::table('telephony_endpoints')->insert([
                'name' => 'Forward destination',
                'type' => 'cell',
                'destination' => trim($forwardTo),
                'enabled' => true,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_endpoints');
    }
};
