<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->boolean('portal_signature_required')->default(false)->after('authorization_language');
        });

        Schema::table('approval_events', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('portal_signature_required');
        });

        Schema::table('approval_events', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};
