<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'sync_queue_id',
        'action',
        'message',
        'status',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function syncQueueItem(): BelongsTo
    {
        return $this->belongsTo(SyncQueue::class, 'sync_queue_id');
    }
}
