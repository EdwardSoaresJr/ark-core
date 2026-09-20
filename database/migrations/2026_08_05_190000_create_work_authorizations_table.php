<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->string('package_type', 32);
            $table->string('status', 32)->default('authorized');
            $table->string('scope_key', 64)->nullable();
            $table->foreignId('repair_order_concern_id')->nullable()->constrained('repair_order_concerns')->nullOnDelete();
            $table->foreignId('repair_order_work_group_id')->nullable()->constrained('repair_order_work_groups')->nullOnDelete();
            $table->foreignId('repair_order_line_id')->nullable()->constrained('repair_order_lines')->nullOnDelete();
            $table->string('outcome', 48)->nullable();
            $table->text('recommendation')->nullable();
            $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('escalates_to_authorization_id')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'package_type', 'status'], 'wa_ro_type_status_idx');
            $table->index(['repair_order_concern_id'], 'wa_concern_idx');
        });

        Schema::table('work_authorizations', function (Blueprint $table) {
            $table->foreign('escalates_to_authorization_id', 'wa_escalates_to_fk')
                ->references('id')
                ->on('work_authorizations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_authorizations');
    }
};
