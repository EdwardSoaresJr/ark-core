<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_memory_suggestion_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_key', 64);
            $table->string('suggestion_id', 128)->nullable();
            $table->string('outcome', 32);
            $table->string('surface', 64);
            $table->string('query', 255)->nullable();
            $table->unsignedBigInteger('repair_order_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamps();

            $table->index(['provider_key', 'outcome'], 'sm_suggest_events_provider_outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_memory_suggestion_events');
    }
};
