<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->boolean('drop_off')->default(false)->after('appointment');
            $table->boolean('needs_shuttle')->default(false)->after('drop_off');
            $table->boolean('warranty')->default(false)->after('needs_shuttle');
            $table->boolean('fleet')->default(false)->after('warranty');
            $table->text('advisor_notes')->nullable()->after('concern');
        });
    }

    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropColumn(['drop_off', 'needs_shuttle', 'warranty', 'fleet', 'advisor_notes']);
        });
    }
};
