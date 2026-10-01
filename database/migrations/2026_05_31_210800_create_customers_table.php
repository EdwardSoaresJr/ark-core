<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('customer_type')->default('Retail');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name'], 'customers_name_idx');
            $table->index('phone', 'customers_phone_idx');
            $table->index('email', 'customers_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
