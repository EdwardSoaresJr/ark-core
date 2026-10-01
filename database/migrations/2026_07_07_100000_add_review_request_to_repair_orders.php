<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->boolean('review_request_sent')->nullable()->after('lost_reason_recorded_by');
            $table->text('review_not_requested_reason')->nullable()->after('review_request_sent');
            $table->timestamp('review_request_recorded_at')->nullable()->after('review_not_requested_reason');
            $table->foreignId('review_request_recorded_by')->nullable()->after('review_request_recorded_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('review_request_recorded_by');
            $table->dropColumn([
                'review_request_sent',
                'review_not_requested_reason',
                'review_request_recorded_at',
            ]);
        });
    }
};
