<?php

namespace App\Services;

use App\Models\BackupLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupService
{
    public function list(int $perPage = 15)
    {
        return BackupLog::with('creator')->latest()->paginate($perPage);
    }

    public function create(User $user): BackupLog
    {
        $filename = 'edusync_backup_'.now()->format('Y-m-d_His').'.zip';
        $relativePath = 'backups/'.$filename;
        $fullPath = storage_path('app/'.$relativePath);

        File::ensureDirectoryExists(dirname($fullPath));

        $zip = new ZipArchive;
        if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create backup archive.');
        }

        $tables = Schema::getTableListing();
        $export = [];

        foreach ($tables as $table) {
            $tableName = str_contains($table, '.') ? explode('.', $table)[1] : $table;
            if (in_array($tableName, ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions'], true)) {
                continue;
            }
            $export[$tableName] = DB::table($tableName)->get()->toArray();
        }

        $zip->addFromString('database.json', json_encode($export, JSON_PRETTY_PRINT));
        $zip->close();

        $size = filesize($fullPath);

        return BackupLog::create([
            'filename' => $filename,
            'type' => 'full',
            'size' => $size,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);
    }

    public function restore(BackupLog $backup): void
    {
        $path = storage_path('app/backups/'.$backup->filename);

        if (! file_exists($path)) {
            throw new \RuntimeException('Backup file not found.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Could not open backup archive.');
        }

        $json = $zip->getFromName('database.json');
        $zip->close();

        if (! $json) {
            throw new \RuntimeException('Invalid backup format.');
        }

        $data = json_decode($json, true);

        DB::transaction(function () use ($data) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            }

            foreach ($data as $table => $rows) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                DB::table($table)->truncate();
                foreach (array_chunk($rows, 100) as $chunk) {
                    $records = array_map(fn ($row) => (array) $row, $chunk);
                    if (! empty($records)) {
                        DB::table($table)->insert($records);
                    }
                }
            }

            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        });
    }

    public function downloadPath(BackupLog $backup): string
    {
        return storage_path('app/backups/'.$backup->filename);
    }

    public function delete(BackupLog $backup): void
    {
        $path = $this->downloadPath($backup);
        if (file_exists($path)) {
            unlink($path);
        }
        $backup->delete();
    }
}
