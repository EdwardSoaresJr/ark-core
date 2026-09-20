<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_devices', function (Blueprint $table): void {
            $table->timestamp('last_registered_at')->nullable()->after('status');
            $table->timestamp('last_provisioned_at')->nullable()->after('last_registered_at');
            $table->string('microbrowser_token', 64)->nullable()->unique()->after('last_provisioned_at');
        });
    }

    public function down(): void
    {
        Schema::table('communication_devices', function (Blueprint $table): void {
            $table->dropUnique(['microbrowser_token']);
            $table->dropColumn([
                'last_registered_at',
                'last_provisioned_at',
                'microbrowser_token',
            ]);
        });
    }
};
