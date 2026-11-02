<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Master spec section 47 (seed data): one demo login per role, documented
 * in INSTALL.md. These are development conveniences, not meant to be
 * pushed as-is to a live production database - INSTALL.md tells the
 * administrator to change or remove them after first login.
 */
class UserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Passw0rd!123';

    public function run(): void
    {
        $accounts = [
            ['role' => 'Super Administrator', 'name' => 'System Administrator', 'email' => 'admin@school.local'],
            ['role' => 'School Administrator', 'name' => 'School Admin', 'email' => 'school.admin@school.local'],
            ['role' => 'Principal', 'name' => 'Principal Office', 'email' => 'principal@school.local'],
            ['role' => 'Teacher', 'name' => 'Demo Teacher', 'email' => 'teacher@school.local'],
            ['role' => 'Accountant', 'name' => 'Demo Accountant', 'email' => 'accountant@school.local'],
            ['role' => 'Librarian', 'name' => 'Demo Librarian', 'email' => 'librarian@school.local'],
            ['role' => 'Storekeeper', 'name' => 'Demo Storekeeper', 'email' => 'storekeeper@school.local'],
            ['role' => 'Receptionist', 'name' => 'Demo Receptionist', 'email' => 'receptionist@school.local'],
            ['role' => 'Attendance Clerk', 'name' => 'Demo Attendance Clerk', 'email' => 'attendance.clerk@school.local'],
            ['role' => 'Parent', 'name' => 'Demo Parent', 'email' => 'parent@school.local'],
            ['role' => 'Student', 'name' => 'Demo Student', 'email' => 'student@school.local'],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'must_change_password' => false,
                    'preferred_locale' => 'en',
                ]
            );

            $user->syncRoles([$account['role']]);
        }
    }
}
