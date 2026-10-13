<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_provider_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->unique();
            $table->string('account_sid');
            $table->text('auth_token');
            $table->string('from_number');
            $table->string('api_base');
            $table->timestamps();
        });

        Schema::create('messaging_send_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_key')->unique();
            $table->string('provider_message_sid');
            $table->timestamps();
        });

        Schema::create('messaging_webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_webhook_receipts');
        Schema::dropIfExists('messaging_send_receipts');
        Schema::dropIfExists('messaging_provider_credentials');
    }
};
