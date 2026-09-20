<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2 — User owns Shop. Extends platform Shop authority; no Tenant / provisioning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_shops', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->foreignId('owner_user_id')
                ->nullable()
                ->unique()
                ->after('uuid')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('phone', 32)->nullable()->after('display_name');
            $table->string('email')->nullable()->after('phone');
            $table->string('timezone', 64)->nullable()->after('email');
            $table->string('country', 2)->nullable()->after('timezone');
            $table->string('state', 64)->nullable()->after('country');
            $table->string('city', 120)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('platform_shops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn([
                'uuid',
                'phone',
                'email',
                'timezone',
                'country',
                'state',
                'city',
            ]);
        });
    }
};
