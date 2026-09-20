<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->text('summary')->change();
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->string('summary')->change();
        });
    }
};
