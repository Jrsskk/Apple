<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'grade_level',
        'teacher_id',
        'academic_year_id',
        'status',
    ];

    public static function generateUniqueCode(string $name): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]+/', '-', trim($name));
        $base = strtoupper(trim((string) $normalized, '-')) ?: 'SUBJECT';
        $candidate = $base;
        $suffix = 1;

        while (static::withTrashed()->where('code', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
