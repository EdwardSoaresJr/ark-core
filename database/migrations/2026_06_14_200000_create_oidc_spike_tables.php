<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oidc_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 64)->unique();
            $table->string('name');
            $table->string('client_secret');
            $table->json('redirect_uris');
            $table->string('required_product', 64);
            $table->boolean('is_confidential')->default(true);
            $table->timestamps();
        });

        Schema::create('oidc_signing_keys', function (Blueprint $table) {
            $table->id();
            $table->string('kid', 64)->unique();
            $table->string('algorithm', 16)->default('RS256');
            $table->timestamp('revoked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('oidc_authorization_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 128)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('oidc_client_id')->constrained('oidc_clients')->cascadeOnDelete();
            $table->string('redirect_uri');
            $table->string('code_challenge', 128);
            $table->string('code_challenge_method', 16)->default('S256');
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['code', 'expires_at']);
        });

        Schema::create('user_product_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_slug', 64);
            $table->boolean('granted')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'product_slug'], 'user_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_product_access');
        Schema::dropIfExists('oidc_authorization_codes');
        Schema::dropIfExists('oidc_signing_keys');
        Schema::dropIfExists('oidc_clients');
    }
};
