<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('student.{student}', function (User $user, int $student): bool {
    return $user->isStudent() && $user->id === $student;
});