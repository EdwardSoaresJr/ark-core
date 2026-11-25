<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user Job Board List/Cards preference (tenant users table).
 * Null means unset — resolve defaults to Cards and may one-time migrate a legacy cookie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_board_view', 16)->nullable()->after('schedule_board_view');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('job_board_view');
        });
    }
};
