<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_master_admin')->default(false)->after('is_active');
        });

        $masterAdminId = User::query()
            ->role(ArkRole::Admin->value)
            ->orderBy('id')
            ->value('id');

        if ($masterAdminId !== null) {
            User::query()->whereKey($masterAdminId)->update(['is_master_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_master_admin');
        });
    }
};
