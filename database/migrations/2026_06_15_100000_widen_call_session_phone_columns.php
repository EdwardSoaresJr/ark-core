<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->string('from_number', 128)->change();
            $table->string('to_number', 128)->change();
            $table->string('normalized_from', 128)->change();
            $table->string('normalized_to', 128)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->string('from_number', 32)->change();
            $table->string('to_number', 32)->change();
            $table->string('normalized_from', 32)->change();
            $table->string('normalized_to', 32)->nullable()->change();
        });
    }
};
