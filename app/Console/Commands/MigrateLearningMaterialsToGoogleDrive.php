<?php

namespace App\Console\Commands;

use App\Models\LearningMaterial;
use App\Services\GoogleDriveStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateLearningMaterialsToGoogleDrive extends Command
{
    protected $signature = 'materials:migrate-to-google-drive';

    protected $description = 'Move existing local learning materials to Google Drive';

    public function handle(GoogleDriveStorage $drive): int
    {
        $disk = Storage::disk('local');
        $migrated = 0;
        $failed = false;

        LearningMaterial::withTrashed()
            ->whereNull('google_drive_file_id')
            ->chunkById(100, function ($materials) use ($disk, $drive, &$migrated, &$failed): bool {
                foreach ($materials as $material) {
                    if (! $material->file_path || ! $disk->exists($material->file_path)) {
                        $this->error("Material {$material->id} has no accessible local file.");
                        $failed = true;

                        return false;
                    }

                    $oldPath = $material->file_path;
                    $name = $material->original_file_name
                        ?: basename($oldPath);
                    $mimeType = $material->file_type
                        ?: $disk->mimeType($oldPath)
                        ?: 'application/octet-stream';
                    $uploaded = $drive->upload($disk->path($oldPath), $name, $mimeType);

                    try {
                        $material->update([
                            'file_path' => null,
                            'google_drive_file_id' => $uploaded['id'],
                            'original_file_name' => $uploaded['name'],
                            'file_type' => $uploaded['mimeType'],
                            'file_size' => $uploaded['size'],
                        ]);
                    } catch (Throwable $exception) {
                        try {
                            $drive->delete($uploaded['id']);
                        } catch (Throwable $cleanupException) {
                            Log::error('Unable to clean up a failed material migration upload.', [
                                'material_id' => $material->id,
                                'file_id' => $uploaded['id'],
                                'exception' => $cleanupException,
                            ]);
                        }

                        throw $exception;
                    }

                    if (! $disk->delete($oldPath)) {
                        $this->error("Material {$material->id} was uploaded but the local copy could not be removed.");
                        $failed = true;

                        return false;
                    }

                    $migrated++;
                }

                return true;
            });

        $this->info("Migrated {$migrated} learning material file(s) to Google Drive.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
