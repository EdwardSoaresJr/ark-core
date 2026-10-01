<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_extensions', function (Blueprint $table) {
            $table->id();
            $table->string('extension', 16);
            $table->string('display_name', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_type', 24);
            $table->boolean('enabled')->default(true);
            $table->string('location', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('extension', 'tel_ext_number_unique');
            $table->index(['enabled', 'extension'], 'tel_ext_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_extensions');
    }
};
