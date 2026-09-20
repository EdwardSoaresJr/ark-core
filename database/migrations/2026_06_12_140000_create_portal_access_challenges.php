<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_access_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('destination');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['destination', 'expires_at'], 'portal_access_dest_exp_idx');
            $table->index(['customer_id', 'destination'], 'portal_access_cust_dest_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_access_challenges');
    }
};
