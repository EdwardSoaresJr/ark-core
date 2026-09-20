<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ProvisioningRequest workflow authority — no infrastructure adapters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_provisioning_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('platform_shops')->cascadeOnDelete();
            $table->foreignId('deployment_id')->constrained('platform_deployments')->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('source', 32);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status'], 'plat_prov_req_shop_status_idx');
            $table->index(['deployment_id', 'status'], 'plat_prov_req_deploy_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_provisioning_requests');
    }
};
