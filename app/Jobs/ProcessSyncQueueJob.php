<?php

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSyncQueueJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId) {}

    public function handle(SyncService $syncService, NotificationService $notificationService): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $results = $syncService->processPendingForUser($user);

        $failed = collect($results)->filter(
            fn ($item) => $item->status === SyncStatus::Failed
        )->count();

        if ($failed > 0) {
            $notificationService->notifySyncFailed($user, "{$failed} sync item(s) failed.");
        } else {
            $notificationService->notifySyncSuccess($user);
        }
    }
}
