<?php

use App\Ark\Operations\RepairOrders\SeedEstimateCompanionPatterns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_companion_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('job_key', 80);
            $table->json('job_needles');
            $table->string('companion_key', 80);
            $table->string('companion_label', 80);
            $table->json('companion_needles');
            $table->unsignedInteger('support_count')->default(0);
            $table->unsignedInteger('exception_count')->default(0);
            $table->string('source', 16)->default('observed');
            $table->timestamps();

            $table->unique(['job_key', 'companion_key'], 'est_comp_job_companion_uq');
            $table->index('job_key', 'est_comp_job_key_idx');
        });

        SeedEstimateCompanionPatterns::install();
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_companion_patterns');
    }
};
