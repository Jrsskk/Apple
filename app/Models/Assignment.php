<?php

namespace App\Models;

use App\Enums\QuizStatus;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'instructions',
        'subject_id',
        'school_class_id',
        'teacher_id',
        'attachment_path',
        'attachment_storage_disk',
        'attachment_file_name',
        'attachment_file_type',
        'attachment_file_size',
        'posted_at',
        'starts_at',
        'deadline',
        'max_score',
        'allow_resubmit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'starts_at' => 'datetime',
            'deadline' => 'datetime',
            'max_score' => 'float',
            'allow_resubmit' => 'boolean',
            'status' => QuizStatus::class,
        ];
    }

    protected $appends = ['due_at'];

    public function getDueAtAttribute(): ?string
    {
        return $this->deadline?->toIso8601String();
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

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
