<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table): void {
            $table->string('billing_posture', 24)->default('default')->change();
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_concerns', function (Blueprint $table): void {
            $table->string('billing_posture', 16)->default('default')->change();
        });
    }
};
