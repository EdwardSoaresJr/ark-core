<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('call_sessions') || Schema::hasColumn('call_sessions', 'hosted_outcome')) {
            return;
        }

        Schema::table('call_sessions', function (Blueprint $table): void {
            $table->string('hosted_outcome', 32)->nullable();
            $table->unsignedInteger('dial_duration_seconds')->nullable();
            $table->string('disposition_origin', 16)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('call_sessions') || ! Schema::hasColumn('call_sessions', 'hosted_outcome')) {
            return;
        }

        Schema::table('call_sessions', function (Blueprint $table): void {
            $table->dropColumn(['hosted_outcome', 'dial_duration_seconds', 'disposition_origin']);
        });
    }
};
