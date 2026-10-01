<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('originating_repair_order_id')->nullable()->constrained('repair_orders')->nullOnDelete();
            $table->foreignId('originating_inspection_id')->nullable()->constrained('inspections')->nullOnDelete();
            $table->foreignId('originating_inspection_item_id')->nullable()->constrained('inspection_items')->nullOnDelete();
            $table->foreignId('originating_repair_order_concern_id')->nullable()->constrained('repair_order_concerns')->nullOnDelete();
            $table->string('title');
            $table->text('customer_description')->nullable();
            $table->text('advisor_note')->nullable();
            $table->string('lifecycle', 20)->default('open');
            $table->timestamp('discovered_at');
            $table->unsignedInteger('discovered_mileage')->nullable();
            $table->string('urgency', 32)->default('soon');
            $table->boolean('safety_related')->default(false);
            $table->string('due_kind', 32)->default('none');
            $table->date('due_on')->nullable();
            $table->unsignedInteger('due_mileage')->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->foreignId('follow_up_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('follow_up_completed_at')->nullable();
            $table->timestamp('follow_up_snoozed_until')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('resolved_mileage')->nullable();
            $table->string('resolved_reason', 40)->nullable();
            $table->text('resolved_note')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->string('dismissal_reason', 64)->nullable();
            $table->text('dismissal_note')->nullable();
            $table->string('source_kind', 40)->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'lifecycle'], 'rec_vehicle_lifecycle_idx');
            $table->index(['customer_id', 'lifecycle'], 'rec_customer_lifecycle_idx');
            $table->index(['follow_up_at'], 'rec_follow_up_at_idx');
            $table->index(['originating_inspection_item_id'], 'rec_insp_item_idx');
            $table->index(['originating_repair_order_concern_id'], 'rec_orig_concern_idx');
        });

        Schema::create('recommendation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->timestamp('occurred_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('repair_order_id')->nullable()->constrained('repair_orders')->nullOnDelete();
            $table->foreignId('repair_order_concern_id')->nullable()->constrained('repair_order_concerns')->nullOnDelete();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->string('reason_code', 40)->nullable();
            $table->text('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['recommendation_id', 'occurred_at'], 'rec_evt_rec_occurred_idx');
            $table->index(['repair_order_id', 'type'], 'rec_evt_ro_type_idx');
            $table->index(['type', 'reason_code'], 'rec_evt_type_reason_idx');
        });

        Schema::create('recommendation_estimate_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_order_concern_id')->constrained('repair_order_concerns')->restrictOnDelete();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->timestamps();

            $table->unique(['recommendation_id', 'repair_order_id'], 'rec_est_ro_unique');
            $table->index(['repair_order_concern_id'], 'rec_est_concern_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_estimate_links');
        Schema::dropIfExists('recommendation_events');
        Schema::dropIfExists('recommendations');
    }
};
