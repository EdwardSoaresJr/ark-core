<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_settings', 'maintenance_engine_oil')) {
                $table->json('maintenance_engine_oil')->nullable()->after('oil_change_interval_miles');
            }
        });

        Schema::create('maintenance_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('status', 32)->default('active');
            $table->foreignId('repair_order_concern_id')->nullable()->constrained('repair_order_concerns')->nullOnDelete();
            $table->foreignId('repair_order_work_group_id')->nullable()->constrained('repair_order_work_groups')->nullOnDelete();
            $table->foreignId('repair_order_line_id')->nullable()->constrained('repair_order_lines')->nullOnDelete();
            $table->boolean('reset_reminder')->default(true);
            $table->string('prepared_oil_brand', 120)->nullable();
            $table->string('prepared_viscosity', 32)->nullable();
            $table->decimal('prepared_quantity_qt', 8, 2)->nullable();
            $table->string('prepared_filter_part', 120)->nullable();
            $table->string('prepared_washer', 32)->nullable();
            $table->unsignedBigInteger('current_event_id')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'kind', 'status'], 'maint_svc_ro_kind_status_idx');
            $table->index(['vehicle_id', 'kind'], 'maint_svc_vehicle_kind_idx');
        });

        Schema::create('maintenance_service_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_service_id')->constrained('maintenance_services')->cascadeOnDelete();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->unsignedInteger('service_sequence');
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('superseded_by_event_id')->nullable()->constrained('maintenance_service_events')->nullOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at');
            $table->unsignedInteger('service_mileage')->nullable();
            $table->unsignedInteger('next_due_mileage')->nullable();
            $table->string('oil_brand', 120)->nullable();
            $table->string('viscosity', 32)->nullable();
            $table->decimal('quantity_qt', 8, 2)->nullable();
            $table->string('filter_part', 120)->nullable();
            $table->string('washer', 32)->nullable();
            $table->boolean('reset_reminder')->default(true);
            $table->timestamps();

            $table->unique(
                ['vehicle_id', 'kind', 'service_sequence', 'revision'],
                'maint_evt_vehicle_kind_seq_rev_uq',
            );
            $table->index(['vehicle_id', 'kind', 'superseded_by_event_id'], 'maint_evt_current_lookup_idx');
            $table->index(['maintenance_service_id'], 'maint_evt_service_idx');
        });

        Schema::table('maintenance_services', function (Blueprint $table) {
            $table->foreign('current_event_id')
                ->references('id')
                ->on('maintenance_service_events')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_services', function (Blueprint $table) {
            $table->dropForeign(['current_event_id']);
        });

        Schema::dropIfExists('maintenance_service_events');
        Schema::dropIfExists('maintenance_services');

        Schema::table('shop_settings', function (Blueprint $table) {
            if (Schema::hasColumn('shop_settings', 'maintenance_engine_oil')) {
                $table->dropColumn('maintenance_engine_oil');
            }
        });
    }
};
