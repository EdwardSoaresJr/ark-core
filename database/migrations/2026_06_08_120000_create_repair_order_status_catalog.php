<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ro_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 64);
            $table->boolean('is_system')->default(true);
            $table->boolean('requires_mileage_in')->default(false);
            $table->boolean('requires_mileage_out')->default(false);
            $table->string('dashboard_group_slug', 64)->nullable();
            $table->string('dashboard_group_name', 64)->nullable();
            $table->boolean('show_on_advisor_board')->default(true);
            $table->boolean('show_on_technician_board')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('requires_variant')->default(false);
            $table->boolean('enforce_standard_close_rules')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('customer_status_copy')->nullable();
            $table->string('color', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('ro_status_variants', function (Blueprint $table) {
            $table->id();
            $table->string('status_slug', 32);
            $table->string('variant_key', 32);
            $table->string('name', 64);
            $table->boolean('affects_metrics')->default(true);
            $table->boolean('bypass_standard_close_rules')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['status_slug', 'variant_key'], 'ro_status_variant_unique');
            $table->foreign('status_slug', 'ro_status_variants_status_fk')
                ->references('slug')
                ->on('ro_statuses')
                ->cascadeOnDelete();
        });

        Schema::create('ro_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('from_status_slug', 32);
            $table->string('to_status_slug', 32);
            $table->timestamps();

            $table->unique(['from_status_slug', 'to_status_slug'], 'ro_status_transition_unique');
            $table->foreign('from_status_slug', 'ro_status_transitions_from_fk')
                ->references('slug')
                ->on('ro_statuses')
                ->cascadeOnDelete();
            $table->foreign('to_status_slug', 'ro_status_transitions_to_fk')
                ->references('slug')
                ->on('ro_statuses')
                ->cascadeOnDelete();
        });

        Schema::create('ro_status_trans_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transition_id')->constrained('ro_status_transitions')->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamps();

            $table->unique(['transition_id', 'role'], 'ro_status_trans_role_unique');
        });

        Schema::table('repair_orders', function (Blueprint $table) {
            $table->string('close_variant_key', 32)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropColumn('close_variant_key');
        });

        Schema::dropIfExists('ro_status_trans_roles');
        Schema::dropIfExists('ro_status_transitions');
        Schema::dropIfExists('ro_status_variants');
        Schema::dropIfExists('ro_statuses');
    }
};
