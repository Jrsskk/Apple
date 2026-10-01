<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'employee_number' => 'DEMO-ADM',
                'first_name' => 'Demo',
                'last_name' => 'Administrator',
                'email' => 'admin.demo@edusync.test',
                'username' => 'demo_admin',
                'role' => UserRole::Admin,
            ],
            [
                'employee_number' => 'DEMO-TCH',
                'first_name' => 'Demo',
                'last_name' => 'Teacher',
                'email' => 'teacher.demo@edusync.test',
                'username' => 'demo_teacher',
                'role' => UserRole::Teacher,
            ],
            [
                'employee_number' => 'DEMO-STU',
                'first_name' => 'Demo',
                'last_name' => 'Student',
                'email' => 'student.demo@edusync.test',
                'username' => 'demo_student',
                'role' => UserRole::Student,
            ],
        ];

        foreach ($accounts as $account) {
            User::withTrashed()->updateOrCreate(
                ['email' => $account['email']],
                [...$account, 'password' => Hash::make('Demo123!'), 'status' => UserStatus::Active,
                    'email_verified_at' => now(), 'deleted_at' => null]
            );
        }
    }
}
