<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arkademy_content_registry', function (Blueprint $table): void {
            $table->string('bookstack_url')->nullable()->after('bookstack_id');
        });
    }

    public function down(): void
    {
        Schema::table('arkademy_content_registry', function (Blueprint $table): void {
            $table->dropColumn('bookstack_url');
        });
    }
};
