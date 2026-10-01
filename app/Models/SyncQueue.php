<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncQueue extends Model
{
    protected $table = 'sync_queue';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_id',
        'sync_uuid',
        'entity_type',
        'entity_id',
        'action',
        'payload',
        'checksum',
        'status',
        'attempts',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => SyncStatus::class,
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }
}
