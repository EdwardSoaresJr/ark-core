<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->unsignedInteger('estimate_version')->default(1)->after('status');
            $table->foreignId('estimate_version_actor_id')->nullable()->after('estimate_version')->constrained('users')->nullOnDelete();
            $table->timestamp('estimate_version_at')->nullable()->after('estimate_version_actor_id');
        });

        Schema::create('repair_order_worksheet_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_token', 64);
            $table->string('surface', 16);
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['repair_order_id', 'session_token'], 'ro_ws_session_unique');
            $table->index(['repair_order_id', 'expires_at'], 'ro_ws_presence_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_order_worksheet_sessions');

        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('estimate_version_actor_id');
            $table->dropColumn(['estimate_version', 'estimate_version_at']);
        });
    }
};
