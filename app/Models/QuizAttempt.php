<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    protected $fillable = [
        'quiz_id', 'student_id', 'attempt_number', 'sync_uuid',
        'started_at', 'completed_at', 'submitted_at', 'synced_at',
        'device_id', 'score', 'percentage', 'correct_count',
        'incorrect_count', 'total_points', 'passed', 'status', 'integrity_log',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'synced_at' => 'datetime',
            'passed' => 'boolean',
            'integrity_log' => 'array',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function user(): BelongsTo
    {
        return $this->student();
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function integrityEvents(): HasMany
    {
        return $this->hasMany(IntegrityEvent::class);
    }
}
