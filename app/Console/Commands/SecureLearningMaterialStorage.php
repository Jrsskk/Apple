<?php

namespace App\Console\Commands;

use App\Models\LearningMaterial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SecureLearningMaterialStorage extends Command
{
    protected $signature = 'materials:secure-storage';

    protected $description = 'Move existing learning materials from public to private storage';

    public function handle(): int
    {
        $publicDisk = Storage::disk('public');
        $privateDisk = Storage::disk('local');
        $moved = 0;

        foreach (LearningMaterial::withTrashed()->get(['id', 'file_path']) as $material) {
            $path = $material->file_path;
            if (! str_starts_with($path, 'materials/') || str_contains($path, '..')) {
                $this->error("Material {$material->id} has an invalid storage path.");

                return self::FAILURE;
            }

            if ($privateDisk->exists($path) || ! $publicDisk->exists($path)) {
                continue;
            }

            $stream = $publicDisk->readStream($path);
            if (! is_resource($stream)) {
                $this->error("Material {$material->id} could not be read from public storage.");

                return self::FAILURE;
            }

            try {
                $stored = $privateDisk->writeStream($path, $stream);
            } catch (\Throwable $exception) {
                throw new RuntimeException("Material {$material->id} could not be copied to private storage.", previous: $exception);
            } finally {
                fclose($stream);
            }

            if (! $stored || ! $privateDisk->exists($path)) {
                $this->error("Material {$material->id} could not be verified in private storage.");

                return self::FAILURE;
            }

            if (! $publicDisk->delete($path)) {
                $this->error("Material {$material->id} was copied but could not be removed from public storage.");

                return self::FAILURE;
            }

            $moved++;
        }

        $this->info("Moved {$moved} learning material file(s) to private storage.");

        return self::SUCCESS;
    }
}
