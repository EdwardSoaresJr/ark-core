<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'default_parts_catalog')) {
                $table->string('default_parts_catalog', 32)->nullable();
            }

            if (! Schema::hasColumn('users', 'default_labor_guide')) {
                $table->string('default_labor_guide', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['default_parts_catalog', 'default_labor_guide']);
        });
    }
};
