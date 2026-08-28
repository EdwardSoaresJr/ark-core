<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('source', 32)->default('upload');
            $table->string('storage_path', 500);
            $table->string('content_type', 120);
            $table->string('original_name', 255)->nullable();
            $table->unsignedInteger('byte_size')->default(0);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('taken_at')->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('visibility', 16)->default('internal');
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('first_customer_viewed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['repair_order_id', 'visibility'], 'evidence_ro_visibility_idx');
            $table->index(['repair_order_id', 'deleted_at', 'sort_order'], 'evidence_ro_active_sort_idx');
        });

        Schema::create('evidence_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidence')->cascadeOnDelete();
            $table->string('attachable_type', 160);
            $table->unsignedBigInteger('attachable_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['evidence_id', 'attachable_type', 'attachable_id'], 'evidence_attach_unique');
            $table->index(['attachable_type', 'attachable_id', 'is_primary'], 'evidence_attach_target_idx');
        });

        Schema::create('evidence_visibility_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidence')->cascadeOnDelete();
            $table->string('old_visibility', 16)->nullable();
            $table->string('new_visibility', 16);
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');

            $table->index(['evidence_id', 'changed_at'], 'evidence_vis_hist_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_visibility_history');
        Schema::dropIfExists('evidence_attachments');
        Schema::dropIfExists('evidence');
    }
};
