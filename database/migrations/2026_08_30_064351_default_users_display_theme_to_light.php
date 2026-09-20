<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fresh installs / new user rows default to light.
 *
 * Does NOT rewrite existing user preferences (system/dark/light stay as stored).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'display_theme')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY display_theme VARCHAR(16) NOT NULL DEFAULT 'light'");
        } elseif ($driver === 'sqlite') {
            // SQLite ignores column default changes for existing tables in many Laravel setups;
            // application-layer DisplayTheme::default() covers fresh rows.
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'display_theme')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY display_theme VARCHAR(16) NOT NULL DEFAULT 'system'");
        }
    }
};
