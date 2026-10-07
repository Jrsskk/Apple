<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningMaterial extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'file_path',
        'storage_disk',
        'google_drive_file_id',
        'original_file_name',
        'file_type',
        'file_size',
        'subject_id',
        'school_class_id',
        'uploaded_by',
    ];

    protected $appends = ['file_url', 'download_url'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function scopeForConsistentClassSubject(Builder $query): Builder
    {
        return $query->whereHas('schoolClass', function (Builder $classQuery) {
            $classQuery
                ->whereColumn('school_classes.subject_id', 'learning_materials.subject_id')
                ->whereHas('subject');
        });
    }

    public function hasConsistentClassSubject(): bool
    {
        return $this->schoolClass()
            ->where('subject_id', $this->subject_id)
            ->whereHas('subject')
            ->exists();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->google_drive_file_id && ! $this->file_path) {
            return null;
        }

        return route('api.v1.materials.file', $this);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (! $this->exists) {
            return null;
        }

        return route('api.v1.materials.file', ['material' => $this, 'download' => 1]);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'file_url' => $this->file_url,
            'download_url' => $this->download_url,
            'class_id' => $this->school_class_id,
        ]);
    }
}
