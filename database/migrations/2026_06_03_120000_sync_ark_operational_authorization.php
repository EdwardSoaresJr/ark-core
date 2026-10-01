<?php

use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new ArkAuthorizationSeeder)->run();
    }

    public function down(): void
    {
        // Permissions are additive operational rails; do not strip on rollback.
    }
};
