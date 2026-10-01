<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->json('appointment_request_availability')->nullable()->after('scheduling_hours');
        });

        Schema::create('appointment_request_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('mode', 16);
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('date', 'appt_req_exc_date_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_request_exceptions');

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('appointment_request_availability');
        });
    }
};
