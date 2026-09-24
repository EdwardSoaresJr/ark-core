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
        // Capabilities stay in place on rollback so existing role grants are not stripped mid-release.
    }
};
