<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform Cluster / Shop / Deployment scaffolding.
 * No production behavior — authorities for future provisioning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clusters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->string('type', 32);
            $table->string('status', 32);
            $table->string('deployment_target');
            $table->string('ingress_endpoint');
            $table->string('current_version')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_shops', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('legal_name')->nullable();
            $table->string('display_name');
            $table->string('status', 32);
            $table->timestamps();
        });

        Schema::create('platform_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained('platform_shops')->cascadeOnDelete();
            $table->foreignId('cluster_id')->nullable()->constrained('clusters')->nullOnDelete();
            $table->string('profile', 32);
            $table->timestamps();

            $table->index('cluster_id', 'plat_deploy_cluster_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_deployments');
        Schema::dropIfExists('platform_shops');
        Schema::dropIfExists('clusters');
    }
};
