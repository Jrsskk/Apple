<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Teacher], true);
    }

    public function view(User $user, Subject $subject): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->role === UserRole::Admin;
    }
}
