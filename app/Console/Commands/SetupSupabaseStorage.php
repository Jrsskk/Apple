<?php

namespace App\Console\Commands;

use App\Exceptions\SupabaseStorageException;
use App\Services\SupabaseStorage;
use Illuminate\Console\Command;

class SetupSupabaseStorage extends Command
{
    protected $signature = 'supabase:storage-setup';

    protected $description = 'Create the private EduSync buckets in Supabase Storage';

    public function handle(SupabaseStorage $storage): int
    {
        try {
            $storage->ensureBuckets();
        } catch (SupabaseStorageException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Supabase Storage buckets are ready: materials, assignments, profiles.');

        return self::SUCCESS;
    }
}
