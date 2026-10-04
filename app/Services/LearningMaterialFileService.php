<?php

namespace App\Services;

use App\Models\LearningMaterial;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

class LearningMaterialFileService
{
    public function __construct(
        private readonly GoogleDriveStorage $drive,
        private readonly SupabaseStorage $supabase,
    ) {}

    public function response(LearningMaterial $material, bool $download = false): StreamedResponse
    {
        if ($material->storage_disk === 'supabase') {
            abort_unless($material->file_path, 404, 'The material file could not be found.');
            $stream = $this->supabase->download('materials', $material->file_path);
            $contentType = $material->file_type ?: 'application/octet-stream';
        } elseif ($material->google_drive_file_id) {
            $stream = $this->drive->download($material->google_drive_file_id);
            $contentType = $material->file_type ?: 'application/octet-stream';
        } else {
            $disk = Storage::disk('local');
            abort_unless(
                $material->file_path && $disk->exists($material->file_path),
                404,
                'The material file could not be found.',
            );
            $stream = $disk->readStream($material->file_path);
            abort_unless(is_resource($stream), 404, 'The material file could not be read.');
            $contentType = $disk->mimeType($material->file_path) ?: $material->file_type ?: 'application/octet-stream';
        }

        $extension = pathinfo($material->original_file_name ?: $material->file_path ?: '', PATHINFO_EXTENSION);
        $extension = strtolower($extension);
        if (str_contains(strtolower($contentType), 'pdf')) {
            $contentType = 'application/pdf';
            $extension = $extension ?: 'pdf';
        }
        $browserMimeTypes = [
            'pdf' => 'application/pdf',
            'avif' => 'image/avif',
            'bmp' => 'image/bmp',
            'gif' => 'image/gif',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'm4v' => 'video/x-m4v',
            'mov' => 'video/quicktime',
            'mp4' => 'video/mp4',
            'ogv' => 'video/ogg',
            'webm' => 'video/webm',
        ];
        if (isset($browserMimeTypes[$extension])) {
            $contentType = $browserMimeTypes[$extension];
        } elseif ($contentType === 'application/octet-stream' && $extension !== '') {
            $contentType = MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? $contentType;
        }
        $filename = $material->original_file_name ?: $material->title;
        if ($extension !== '' && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== strtolower($extension)) {
            $filename .= '.'.$extension;
        }

        $response = new StreamedResponse(function () use ($stream, $material): void {
            try {
                if (is_string($stream)) {
                    echo $stream;
                } elseif (is_resource($stream)) {
                    fpassthru($stream);
                } else {
                    while (! $stream->eof()) {
                        echo $stream->read(8192);
                    }
                }
            } catch (Throwable $exception) {
                Log::error('Learning material stream failed.', [
                    'material_id' => $material->id,
                    'google_drive_file_id' => $material->google_drive_file_id,
                    'exception' => $exception,
                ]);

                throw $exception;
            } finally {
                if (is_string($stream)) {
                    return;
                }

                if (is_resource($stream)) {
                    fclose($stream);
                } else {
                    $stream->close();
                }
            }
        }, 200, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                $download ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
                basename($filename),
            ),
        );

        return $response;
    }
}
