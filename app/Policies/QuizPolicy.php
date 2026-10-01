<?php

namespace App\Policies;

use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quiz $quiz): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Teacher) {
            return $quiz->teacher_id === $user->id;
        }

        return $quiz->status === QuizStatus::Published
            && $user->enrolledClasses()
                ->where('school_classes.id', $quiz->school_class_id)
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Teacher || $user->role === UserRole::Admin;
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $user->role === UserRole::Admin || $quiz->teacher_id === $user->id;
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $user->role === UserRole::Admin || $quiz->teacher_id === $user->id;
    }

    public function publish(User $user, Quiz $quiz): bool
    {
        return $this->update($user, $quiz);
    }
}
