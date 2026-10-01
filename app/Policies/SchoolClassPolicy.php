<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SchoolClass $class): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Teacher) {
            return $class->teacher_id === $user->id;
        }

        return $user->enrolledClasses()
            ->where('school_classes.id', $class->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Teacher], true);
    }

    public function update(User $user, SchoolClass $class): bool
    {
        return $user->role === UserRole::Admin || $class->teacher_id === $user->id;
    }

    public function delete(User $user, SchoolClass $class): bool
    {
        return $user->role === UserRole::Admin;
    }
}
