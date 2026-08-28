<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_sms_capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_phone', 20);
            $table->boolean('valid')->nullable();
            $table->string('line_type', 32)->nullable();
            $table->string('carrier_name', 120)->nullable();
            $table->boolean('sms_capable');
            $table->string('reason', 255)->nullable();
            $table->timestamp('checked_at');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique('normalized_phone', 'phone_sms_cap_phone_unique');
            $table->index(['sms_capable', 'checked_at'], 'phone_sms_cap_capable_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_sms_capabilities');
    }
};
