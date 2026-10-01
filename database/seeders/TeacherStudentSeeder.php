<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherStudentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers('TCH', 'Teacher', UserRole::Teacher);
        $this->seedUsers('STU', 'Student', UserRole::Student);
    }

    private function seedUsers(string $prefix, string $label, UserRole $role): void
    {
        for ($number = 1; $number <= 10; $number++) {
            $suffix = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $username = strtolower($label)."_{$suffix}";

            User::withTrashed()->updateOrCreate(
                ['email' => "{$username}@edusync.test"],
                [
                    'employee_number' => "{$prefix}-NEW-{$suffix}",
                    'first_name' => $label,
                    'last_name' => "Number {$number}",
                    'username' => $username,
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'deleted_at' => null,
                ]
            );
        }
    }
}
