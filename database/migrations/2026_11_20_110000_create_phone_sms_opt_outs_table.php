<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_sms_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_phone', 32)->unique();
            $table->string('source', 32);
            $table->string('provider_message_id', 64)->nullable();
            $table->string('error_code', 16)->nullable();
            $table->timestamp('opted_out_at');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_sms_opt_outs');
    }
};
