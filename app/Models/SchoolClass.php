<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SchoolClass extends Model
{
    use SoftDeletes;

    protected $table = 'school_classes';

    protected $fillable = [
        'name', 'section', 'grade_level', 'subject_id', 'teacher_id',
        'academic_year_id', 'schedule', 'room', 'status', 'class_code',
    ];

    protected static function booted(): void
    {
        static::creating(function (SchoolClass $class) {
            if (empty($class->class_code)) {
                $class->class_code = static::generateUniqueClassCode();
            }
        });
    }

    public static function generateUniqueClassCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (static::withTrashed()->where('class_code', $code)->exists());

        return $code;
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('class_code', strtoupper(trim($code)))
            ->where('status', 'active')
            ->first();
    }

    public function ensureClassCode(): self
    {
        if (blank($this->class_code)) {
            $this->forceFill(['class_code' => static::generateUniqueClassCode()])->save();
        }

        return $this;
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} - {$this->section}";
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_students', 'school_class_id', 'student_id')
            ->withPivot(['enrolled_at', 'status'])
            ->withTimestamps();
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(LearningMaterial::class);
    }
}
