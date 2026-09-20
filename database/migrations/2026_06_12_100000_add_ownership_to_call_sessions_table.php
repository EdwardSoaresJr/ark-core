<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->foreignId('owned_by_user_id')
                ->nullable()
                ->after('worked_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('owned_at')->nullable()->after('owned_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owned_by_user_id');
            $table->dropColumn('owned_at');
        });
    }
};
