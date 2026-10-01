<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts_remaining')->default(5);
            $table->unsignedTinyInteger('send_count')->default(1);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->string('created_ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['email', 'created_at'], 'email_verif_email_created_idx');
            $table->index(['created_ip', 'created_at'], 'email_verif_ip_created_idx');
            $table->index(['email', 'verified_at'], 'email_verif_email_verified_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
    }
};
