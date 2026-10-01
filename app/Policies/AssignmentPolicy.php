<?php

namespace App\Policies;

use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Teacher) {
            return $assignment->teacher_id === $user->id;
        }

        return $assignment->status === QuizStatus::Published
            && $user->enrolledClasses()
                ->where('school_classes.id', $assignment->school_class_id)
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Teacher || $user->role === UserRole::Admin;
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $user->role === UserRole::Admin || $assignment->teacher_id === $user->id;
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $user->role === UserRole::Admin || $assignment->teacher_id === $user->id;
    }

    public function submit(User $user, Assignment $assignment): bool
    {
        return $user->role === UserRole::Student
            && $assignment->status === QuizStatus::Published
            && $user->enrolledClasses()
                ->where('school_classes.id', $assignment->school_class_id)
                ->exists();
    }
}
