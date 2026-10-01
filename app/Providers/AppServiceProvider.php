<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use App\Policies\AssignmentPolicy;
use App\Policies\QuizPolicy;
use App\Policies\SchoolClassPolicy;
use App\Policies\SubjectPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Subject::class => SubjectPolicy::class,
        SchoolClass::class => SchoolClassPolicy::class,
        Quiz::class => QuizPolicy::class,
        Assignment::class => AssignmentPolicy::class,
        Announcement::class => AnnouncementPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
