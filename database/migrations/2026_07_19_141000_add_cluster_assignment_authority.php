<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cluster Assignment authority + assignable flag.
 * Placement history — not provisioning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clusters', function (Blueprint $table) {
            $table->boolean('accepting_new_shops')->default(true)->after('status');
        });

        Schema::create('platform_cluster_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('platform_shops')->cascadeOnDelete();
            $table->foreignId('deployment_id')->constrained('platform_deployments')->cascadeOnDelete();
            $table->foreignId('cluster_id')->constrained('clusters')->cascadeOnDelete();
            $table->string('profile', 32);
            $table->string('source', 32);
            $table->string('reason')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('previous_cluster_id')->nullable()->constrained('clusters')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['deployment_id', 'superseded_at'], 'plat_assign_deploy_active_idx');
            $table->index(['cluster_id', 'superseded_at'], 'plat_assign_cluster_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_cluster_assignments');

        Schema::table('clusters', function (Blueprint $table) {
            $table->dropColumn('accepting_new_shops');
        });
    }
};
