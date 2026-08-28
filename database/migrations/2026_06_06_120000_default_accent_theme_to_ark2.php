<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('accent_theme', ['orange', 'blue'])
            ->update(['accent_theme' => 'ark2']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY accent_theme VARCHAR(32) NOT NULL DEFAULT 'ark2'");
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('accent_theme', 32)->default('ark2')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY accent_theme VARCHAR(32) NOT NULL DEFAULT 'orange'");
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('accent_theme', 32)->default('orange')->change();
            });
        }
    }
};
