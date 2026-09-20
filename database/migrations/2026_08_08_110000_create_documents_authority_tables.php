<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents authority — durable paperwork.
 * Freeze: A document exists once. Relationships determine where it appears.
 * The physical document is never duplicated merely to satisfy a relationship.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('repair_order_id')->nullable()->constrained('repair_orders')->nullOnDelete();
            $table->string('type', 40);
            $table->string('title', 255);
            $table->string('description', 1000)->nullable();
            $table->string('storage_path', 500);
            $table->string('content_type', 120);
            $table->string('original_name', 255)->nullable();
            $table->unsignedInteger('byte_size')->default(0);
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->string('source', 32)->default('upload');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->boolean('customer_visible')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['customer_id', 'deleted_at', 'created_at'], 'docs_customer_active_idx');
            $table->index(['repair_order_id', 'deleted_at'], 'docs_ro_active_idx');
            $table->index(['customer_id', 'type', 'deleted_at'], 'docs_customer_type_idx');
        });

        Schema::create('document_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('type', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['document_id', 'occurred_at'], 'doc_events_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_events');
        Schema::dropIfExists('documents');
    }
};
