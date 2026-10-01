<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('theme_label', 64)->default('Open House')->after('name');
            $table->string('background_image_path')->nullable()->after('partner_logo_path');
            $table->json('background_images')->nullable()->after('background_image_path');
            $table->json('schedule')->nullable()->after('drawing_time');
            $table->json('car_care_tips')->nullable()->after('schedule');
            $table->string('partner_spotlight_text', 500)->nullable()->after('partner_logo_path');
            $table->boolean('photo_booth_enabled')->default(true)->after('is_active');
        });

        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_attendee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('disk_path');
            $table->timestamps();

            $table->index(['event_id', 'created_at'], 'evt_photo_event_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_photos');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'theme_label',
                'background_image_path',
                'background_images',
                'schedule',
                'car_care_tips',
                'partner_spotlight_text',
                'photo_booth_enabled',
            ]);
        });
    }
};
