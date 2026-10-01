<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dragon_agent_conversations', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('dragon_agent_conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('station_device_token_id')
                ->nullable()
                ->after('user_id')
                ->constrained('station_device_tokens')
                ->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dragon_agent_conversations', function (Blueprint $table): void {
            $table->dropForeign(['station_device_token_id']);
            $table->dropColumn('station_device_token_id');
            $table->dropForeign(['user_id']);
        });

        Schema::table('dragon_agent_conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
