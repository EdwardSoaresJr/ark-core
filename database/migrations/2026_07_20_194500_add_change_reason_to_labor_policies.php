<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labor_policies', function (Blueprint $table) {
            $table->string('change_reason', 255)->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('labor_policies', function (Blueprint $table) {
            $table->dropColumn('change_reason');
        });
    }
};
