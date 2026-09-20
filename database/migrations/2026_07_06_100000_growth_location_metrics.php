<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_location_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('metric', 64);
            $table->date('report_date');
            $table->unsignedInteger('value')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['metric', 'report_date'], 'growth_loc_metric_report_uq');
            $table->index('report_date', 'growth_loc_metric_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_location_metrics');
    }
};
