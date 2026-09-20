<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_attempts', function (Blueprint $table) {
            $table->foreignId('estimate_access_token_id')
                ->nullable()
                ->after('customer_access_token_id')
                ->constrained('estimate_access_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estimate_access_token_id');
        });
    }
};
