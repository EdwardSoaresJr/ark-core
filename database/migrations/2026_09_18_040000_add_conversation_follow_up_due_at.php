<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('follow_up_due_at')->nullable()->after('resolved_at');
            $table->index('follow_up_due_at', 'conv_follow_up_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conv_follow_up_due_idx');
            $table->dropColumn('follow_up_due_at');
        });
    }
};
