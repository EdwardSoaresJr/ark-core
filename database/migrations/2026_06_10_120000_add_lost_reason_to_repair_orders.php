<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->string('lost_reason_key', 32)->nullable()->after('close_variant_key');
            $table->text('lost_reason_note')->nullable()->after('lost_reason_key');
            $table->timestamp('lost_reason_recorded_at')->nullable()->after('lost_reason_note');
            $table->foreignId('lost_reason_recorded_by')->nullable()->after('lost_reason_recorded_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lost_reason_recorded_by');
            $table->dropColumn([
                'lost_reason_key',
                'lost_reason_note',
                'lost_reason_recorded_at',
            ]);
        });
    }
};
