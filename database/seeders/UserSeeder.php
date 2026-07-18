<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'email' => 'admin@example.com',
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'phone' => '081234567890',
                'role' => Role::SuperAdmin,
            ],
            [
                'email' => 'billing.admin@example.com',
                'name' => 'Admin Penagihan',
                'username' => 'billingadmin',
                'phone' => '081234567891',
                'role' => Role::Admin,
            ],
            [
                'email' => 'budi@example.com',
                'name' => 'Budi',
                'username' => 'budi',
                'phone' => '081234567892',
                'role' => Role::FieldOfficer,
            ],
            [
                'email' => 'siti@example.com',
                'name' => 'Siti',
                'username' => 'siti',
                'phone' => '081234567893',
                'role' => Role::FieldOfficer,
            ],
            [
                'email' => 'agus@example.com',
                'name' => 'Agus',
                'username' => 'agus',
                'phone' => '081234567894',
                'role' => Role::FieldOfficer,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [...$user, 'password' => 'password', 'is_active' => true],
            );
        }
    }
}
