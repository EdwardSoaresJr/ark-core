<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_capture_attempts') || Schema::hasColumn('payment_capture_attempts', 'paid_on')) {
            return;
        }

        Schema::table('payment_capture_attempts', function (Blueprint $table): void {
            $table->string('paid_on', 10)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_capture_attempts') || ! Schema::hasColumn('payment_capture_attempts', 'paid_on')) {
            return;
        }

        Schema::table('payment_capture_attempts', function (Blueprint $table): void {
            $table->dropColumn('paid_on');
        });
    }
};
