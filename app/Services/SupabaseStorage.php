<?php

namespace App\Services;

use App\Exceptions\SupabaseStorageException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SupabaseStorage
{
    public const BUCKETS = ['materials', 'assignments', 'profiles'];

    /**
     * @return array{path: string, name: string, mime_type: string, size: int}
     */
    public function upload(string $bucket, UploadedFile $file, string $directory = ''): array
    {
        $this->assertBucket($bucket);
        $this->ensureBucket($bucket);

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $path = trim($directory, '/');
        $path = $path === '' ? $filename : $path.'/'.$filename;
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $contents = $file->get();

        $this->request('POST', $this->objectUrl($bucket, $path), [
            'Content-Type' => $mimeType,
            'x-upsert' => 'false',
        ], $contents);

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
        ];
    }

    public function download(string $bucket, string $path): string
    {
        $this->assertBucket($bucket);

        return $this->request('GET', $this->objectUrl($bucket, $path))->body();
    }

    public function delete(string $bucket, string $path): void
    {
        $this->assertBucket($bucket);
        $this->assertPath($path);

        $this->request(
            'DELETE',
            $this->storageUrl('/object/'.rawurlencode($bucket)),
            ['Content-Type' => 'application/json'],
            json_encode(['prefixes' => [$path]], JSON_THROW_ON_ERROR),
        );
    }

    public function ensureBuckets(): void
    {
        foreach (self::BUCKETS as $bucket) {
            $this->ensureBucket($bucket);
        }
    }

    private function ensureBucket(string $bucket): void
    {
        $response = $this->send(
            'POST',
            $this->storageUrl('/bucket'),
            ['Content-Type' => 'application/json'],
            json_encode([
                'id' => $bucket,
                'name' => $bucket,
                'public' => false,
            ], JSON_THROW_ON_ERROR),
        );

        if ($response->successful()) {
            return;
        }

        if ($this->bucketAlreadyExists($response)) {
            $updated = $this->send(
                'PUT',
                $this->storageUrl('/bucket/'.rawurlencode($bucket)),
                ['Content-Type' => 'application/json'],
                json_encode(['public' => false], JSON_THROW_ON_ERROR),
            );

            if ($updated->successful()) {
                return;
            }

            $this->fail($updated, 'make bucket '.$bucket.' private');
        }

        $this->fail($response, 'create bucket '.$bucket);
    }

    private function bucketAlreadyExists(Response $response): bool
    {
        if ($response->status() === 409) {
            return true;
        }

        if ($response->status() !== 400) {
            return false;
        }

        $message = implode(' ', array_filter([
            $response->json('message'),
            $response->json('error'),
            $response->json('statusCode'),
        ], 'is_string'));

        return str_contains(strtolower($message), 'already exists');
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        $response = $this->send($method, $url, $headers, $body);

        if (! $response->successful()) {
            $this->fail($response, strtolower($method).' object');
        }

        return $response;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        $serviceRoleKey = config('services.supabase.service_role_key');
        $baseUrl = config('services.supabase.url');

        if (! is_string($serviceRoleKey) || $serviceRoleKey === '' || ! is_string($baseUrl) || $baseUrl === '') {
            throw new SupabaseStorageException(
                'Supabase Storage is not configured. Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY.',
            );
        }

        try {
            $request = Http::withHeaders([
                ...$headers,
                'apikey' => $serviceRoleKey,
            ])->withToken($serviceRoleKey)->acceptJson()->timeout(120);

            return $body === null
                ? $request->send($method, $url)
                : $request->withBody($body, $headers['Content-Type'] ?? 'application/octet-stream')
                    ->send($method, $url);
        } catch (Throwable $exception) {
            Log::error('Supabase Storage request failed.', [
                'url' => $url,
                'method' => $method,
                'exception' => $exception,
            ]);

            throw new SupabaseStorageException(
                'Supabase Storage is unavailable. Check the Supabase URL, credentials, and network connection.',
                previous: $exception,
            );
        }
    }

    private function fail(Response $response, string $action): never
    {
        $message = $response->json('message')
            ?: $response->json('error')
            ?: 'Supabase Storage rejected the request.';

        Log::error('Supabase Storage operation failed.', [
            'action' => $action,
            'status' => $response->status(),
            'message' => $message,
        ]);

        throw new SupabaseStorageException(
            "Supabase Storage could not {$action}. {$message}",
            $response->status(),
        );
    }

    private function storageUrl(string $path): string
    {
        $baseUrl = config('services.supabase.url');
        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new SupabaseStorageException('Set SUPABASE_URL to configure Supabase Storage.');
        }

        return rtrim($baseUrl, '/').'/storage/v1'.$path;
    }

    private function objectUrl(string $bucket, string $path): string
    {
        $this->assertPath($path);
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

        return $this->storageUrl('/object/'.rawurlencode($bucket).'/'.$encodedPath);
    }

    private function assertBucket(string $bucket): void
    {
        if (! in_array($bucket, self::BUCKETS, true)) {
            throw new SupabaseStorageException('The requested Supabase Storage bucket is not allowed.');
        }
    }

    private function assertPath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
            throw new SupabaseStorageException('The requested Supabase Storage path is invalid.');
        }
    }
}
