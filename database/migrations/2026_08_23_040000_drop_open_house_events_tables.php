<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('event_photos');
        Schema::dropIfExists('event_attendees');
        Schema::dropIfExists('events');
    }

    public function down(): void
    {
        // Open House kiosk is retired. Recreate from 2026_07_04 events migrations if needed.
    }
};
