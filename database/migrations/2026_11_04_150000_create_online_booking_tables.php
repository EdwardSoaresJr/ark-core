<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Online booking: shop-configured services and questions, the customer's appointment request,
 * and the service location an appointment happens at (shop, customer site, fleet yard).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->json('online_booking')->nullable();
        });

        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique('booking_services_slug_uq');
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->decimal('labor_hours', 5, 2)->nullable();
            $table->string('concern_prompt', 500)->nullable();
            $table->string('location_mode', 16)->default('shop');
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_public')->default(true);
            $table->json('options')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_questions', function (Blueprint $table) {
            $table->id();
            $table->string('label', 200);
            $table->string('help', 500)->nullable();
            $table->string('type', 16)->default('text');
            $table->json('choices')->nullable();
            $table->boolean('required')->default(false);
            $table->json('service_ids')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('appointment_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('appt_requests_uuid_uq');
            $table->string('submission_key', 64)->nullable()->unique('appt_requests_submission_uq');
            $table->string('status', 16)->default('pending');
            $table->string('mode', 16)->default('request');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('booking_service_id')->nullable()->constrained('booking_services')->nullOnDelete();
            $table->string('service_name', 120)->nullable();
            $table->string('service_option', 120)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('contact_name', 120);
            $table->string('contact_phone', 32);
            $table->string('contact_email')->nullable();
            $table->date('preferred_date')->nullable();
            $table->string('preferred_period', 16)->nullable();
            $table->timestamp('requested_starts_at')->nullable();
            $table->timestamp('requested_ends_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->decimal('workload_hours', 6, 2)->default(1);
            $table->unsignedTinyInteger('vehicle_count')->default(1);
            $table->json('vehicles')->nullable();
            $table->text('concern');
            $table->json('answers')->nullable();
            $table->string('location_mode', 16)->default('shop');
            $table->json('service_address')->nullable();
            $table->json('context')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decline_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'preferred_date'], 'appt_requests_status_date');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('service_location_type', 16)->nullable();
            $table->json('service_address')->nullable();
        });

        $this->makeCreatorNullable();
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['service_location_type', 'service_address']);
        });

        Schema::dropIfExists('appointment_requests');
        Schema::dropIfExists('booking_questions');
        Schema::dropIfExists('booking_services');

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('online_booking');
        });
    }

    /**
     * An appointment a customer books online has no staff creator. Existing rows keep theirs.
     */
    private function makeCreatorNullable(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE appointments MODIFY created_by_user_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->change();
            });
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
