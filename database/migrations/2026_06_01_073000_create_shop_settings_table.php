<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->string('shop_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 64)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->unsignedInteger('default_labor_rate_cents')->default(15000);
            $table->boolean('tax_enabled')->default(false);
            $table->decimal('default_tax_rate', 5, 3)->default(0);
            $table->boolean('taxable_labor')->default(false);
            $table->boolean('taxable_parts')->default(true);
            $table->boolean('shop_fee_enabled')->default(false);
            $table->decimal('shop_fee_rate', 5, 3)->default(0);
            $table->unsignedInteger('shop_fee_cap_cents')->nullable();
            $table->json('parts_matrix')->nullable();
            $table->json('parts_matrices')->nullable();
            $table->json('customer_types')->nullable();
            $table->text('estimate_disclaimer')->nullable();
            $table->text('recommendation_disclaimer')->nullable();
            $table->unsignedSmallInteger('estimate_validity_days')->default(30);
            $table->string('default_concern_priority', 32)->default('normal');
            $table->string('default_estimate_state', 32)->default('estimate');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
};
