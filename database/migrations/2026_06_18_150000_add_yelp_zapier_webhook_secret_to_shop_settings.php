<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Yelp integration removed; column dropped by 2026_06_03_200000_remove_yelp_integration.
    }

    public function down(): void
    {
        //
    }
};
