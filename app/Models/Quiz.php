<?php

namespace App\Models;

use App\Enums\QuizStatus;
use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'instructions',
        'subject_id',
        'school_class_id',
        'teacher_id',
        'starts_at',
        'deadline',
        'duration_minutes',
        'total_points',
        'max_attempts',
        'passing_score',
        'randomize_questions',
        'randomize_choices',
        'show_results',
        'allow_review',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'deadline' => 'datetime',
            'randomize_questions' => 'boolean',
            'randomize_choices' => 'boolean',
            'show_results' => 'boolean',
            'allow_review' => 'boolean',
            'status' => QuizStatus::class,
            'total_points' => 'float',
            'passing_score' => 'float',
        ];
    }

    protected $appends = ['ends_at'];

    public function getEndsAtAttribute(): ?string
    {
        return $this->deadline?->toIso8601String();
    }

    public function isPublished(): bool
    {
        return $this->status === QuizStatus::Published;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
