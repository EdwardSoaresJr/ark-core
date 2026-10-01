<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_generated_common_problems', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique('growth_gen_cp_slug_uq');
            $table->unsignedBigInteger('growth_opportunity_id')->nullable();
            $table->json('problem');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index('published_at', 'growth_gen_cp_pub_idx');
            $table->index('growth_opportunity_id', 'growth_gen_cp_opp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_generated_common_problems');
    }
};
