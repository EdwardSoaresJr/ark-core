<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dragon_service_advisor_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->foreignId('concern_id')->constrained('repair_order_concerns')->cascadeOnDelete();
            $table->string('field', 40);
            $table->text('original_text');
            $table->text('proposal_text');
            $table->text('user_edited_proposal')->nullable();
            $table->text('applied_text')->nullable();
            $table->string('original_hash', 64);
            $table->unsignedBigInteger('dragon_assist_request_id')->nullable();
            $table->foreign('dragon_assist_request_id', 'dsa_apps_assist_fk')
                ->references('id')
                ->on('dragon_assist_requests')
                ->nullOnDelete();
            $table->string('model_name', 80)->nullable();
            $table->string('mode', 40);
            $table->unsignedBigInteger('applied_by_user_id')->nullable();
            $table->foreign('applied_by_user_id', 'dsa_apps_applied_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('reverted_by_user_id')->nullable();
            $table->foreign('reverted_by_user_id', 'dsa_apps_reverted_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'concern_id', 'field'], 'dsa_apps_ro_concern_field_idx');
            $table->index(['concern_id', 'applied_at'], 'dsa_apps_concern_applied_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dragon_service_advisor_applications');
    }
};
