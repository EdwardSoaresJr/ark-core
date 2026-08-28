<?php

namespace Database\Seeders;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the local development operations admin user.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'ARK Admin',
                'email' => 'admin@ark.test',
                'role' => ArkRole::Admin,
            ],
            [
                'name' => 'Demo Advisor',
                'email' => 'advisor@ark.test',
                'role' => ArkRole::Advisor,
            ],
            [
                'name' => 'Demo Technician',
                'email' => 'tech@ark.test',
                'role' => ArkRole::Technician,
            ],
        ];

        foreach ($users as $seedUser) {
            $user = User::updateOrCreate(
                ['email' => $seedUser['email']],
                [
                    'name' => $seedUser['name'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'is_master_admin' => $seedUser['role'] === ArkRole::Admin,
                    'password_set_at' => now(),
                ],
            );

            $user->assignRole($seedUser['role']->value);
        }
    }
}
