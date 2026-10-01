<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if (
            ! $announcement->published_at
            || $announcement->published_at->isFuture()
            || ($announcement->expires_at && $announcement->expires_at->isPast())
        ) {
            return false;
        }

        $allowedAudiences = match ($user->role) {
            UserRole::Student => ['all', 'students'],
            UserRole::Teacher => ['all', 'teachers'],
            default => [],
        };

        if (! in_array($announcement->target_audience, $allowedAudiences, true)) {
            return false;
        }

        if ($announcement->school_class_id === null) {
            return true;
        }

        if ($user->role === UserRole::Teacher) {
            return $announcement->schoolClass?->teacher_id === $user->id;
        }

        return $user->enrolledClasses()
            ->where('school_classes.id', $announcement->school_class_id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Teacher], true);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->role === UserRole::Admin || $announcement->created_by === $user->id;
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->role === UserRole::Admin || $announcement->created_by === $user->id;
    }
}
