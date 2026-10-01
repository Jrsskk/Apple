<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignmentSubmission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'sync_uuid',
        'text_response',
        'file_path',
        'storage_disk',
        'file_name',
        'file_type',
        'file_size',
        'score',
        'feedback',
        'status',
        'submitted_at',
        'synced_at',
        'version',
        'device_id',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'submitted_at' => 'datetime',
            'synced_at' => 'datetime',
            'status' => SubmissionStatus::class,
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function user(): BelongsTo
    {
        return $this->student();
    }
}
