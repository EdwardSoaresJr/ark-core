<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('estimate_companion_patterns');
    }

    public function down(): void
    {
        // The companion catalog is retired. Recreating the empty table is not useful.
    }
};
