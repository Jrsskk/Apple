<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentFileService
{
    public function __construct(private readonly SupabaseStorage $storage) {}

    public function response(
        string $path,
        ?string $disk,
        string $filename,
        bool $download = true,
        ?string $contentType = null,
    ): StreamedResponse {
        if ($disk === 'supabase') {
            $content = $this->storage->download('assignments', $path);
            $response = new StreamedResponse(static function () use ($content): void {
                echo $content;
            });
            $mimeType = $contentType ?: 'application/octet-stream';
        } else {
            $publicDisk = Storage::disk('public');
            abort_unless($publicDisk->exists($path), 404, 'The assignment file could not be found.');
            $stream = $publicDisk->readStream($path);
            abort_unless(is_resource($stream), 404, 'The assignment file could not be read.');
            $mimeType = $publicDisk->mimeType($path) ?: 'application/octet-stream';
            $response = new StreamedResponse(static function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            });
        }

        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
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
