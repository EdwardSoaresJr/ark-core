<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inspection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_order_concern_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 32);
            $table->string('label', 191);
            $table->string('observed_state', 32)->default('not_checked');
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['inspection_id', 'position'], 'insp_items_insp_pos_idx');
            $table->index(['inspection_id', 'category'], 'insp_items_insp_cat_idx');
        });

        Schema::create('inspection_item_measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('value', 64);
            $table->string('unit', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('inspection_item_id', 'insp_meas_item_idx');
        });

        Schema::create('inspection_item_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_item_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32)->default('internal');
            $table->string('storage_path', 255);
            $table->string('content_type', 127);
            $table->string('original_name', 255)->nullable();
            $table->unsignedInteger('byte_size')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('inspection_item_id', 'insp_photo_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_item_photos');
        Schema::dropIfExists('inspection_item_measurements');
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('inspections');
    }
};
