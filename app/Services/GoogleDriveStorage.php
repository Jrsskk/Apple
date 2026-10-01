<?php

namespace App\Services;

use App\Exceptions\GoogleDriveStorageException;
use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;
use Throwable;

class GoogleDriveStorage
{
    /**
     * @return array{id: string, name: string, mimeType: string, size: int}
     */
    public function upload(string $path, string $name, string $mimeType): array
    {
        $folderId = config('services.google_drive.folder_id');
        if (! filled($folderId)) {
            throw new GoogleDriveStorageException('Set GOOGLE_DRIVE_FOLDER_ID to the private Drive destination folder.');
        }

        $client = $this->client();
        $drive = new Drive($client);
        $metadata = new DriveFile([
            'name' => $name,
            'parents' => [$folderId],
        ]);
        $fileSize = filesize($path);
        if ($fileSize === false) {
            throw new GoogleDriveStorageException('The uploaded material could not be read.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new GoogleDriveStorageException('The uploaded material could not be read.');
        }

        try {
            $client->setDefer(true);
            $request = $drive->files->create($metadata, [
                'uploadType' => 'resumable',
                'supportsAllDrives' => true,
                'fields' => 'id,name,mimeType,size',
            ]);
            $uploader = new MediaFileUpload(
                $client,
                $request,
                $mimeType,
                null,
                true,
                1024 * 1024,
            );
            $uploader->setFileSize($fileSize);

            $uploaded = false;
            while (! $uploaded && ! feof($handle)) {
                $uploaded = $uploader->nextChunk(fread($handle, 1024 * 1024));
            }
        } catch (Throwable $exception) {
            Log::error('Google Drive material upload failed.', ['exception' => $exception]);
            throw new GoogleDriveStorageException(
                'Google Drive could not store the material. Check Drive credentials and access.',
                previous: $exception,
            );
        } finally {
            $client->setDefer(false);
            fclose($handle);
        }

        if (! $uploaded || ! $uploaded->getId()) {
            throw new GoogleDriveStorageException('Google Drive did not confirm the material upload.');
        }

        return [
            'id' => $uploaded->getId(),
            'name' => $uploaded->getName() ?: $name,
            'mimeType' => $uploaded->getMimeType() ?: $mimeType,
            'size' => (int) ($uploaded->getSize() ?: $fileSize),
        ];
    }

    public function download(string $fileId): StreamInterface
    {
        try {
            $response = $this->client()->authorize()->request(
                'GET',
                'https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId),
                [
                    'query' => ['alt' => 'media', 'supportsAllDrives' => 'true'],
                    'stream' => true,
                ],
            );

            return $response->getBody();
        } catch (Throwable $exception) {
            Log::error('Google Drive material download failed.', [
                'file_id' => $fileId,
                'exception' => $exception,
            ]);
            if ($exception->getCode() === 404) {
                abort(404, 'The material file could not be found in Google Drive.');
            }

            throw new GoogleDriveStorageException(
                'Google Drive could not retrieve the material.',
                previous: $exception,
            );
        }
    }

    public function delete(string $fileId): void
    {
        try {
            (new Drive($this->client()))->files->update(
                $fileId,
                new DriveFile(['trashed' => true]),
                ['supportsAllDrives' => true, 'fields' => 'id,trashed'],
            );
        } catch (Throwable $exception) {
            Log::error('Google Drive material deletion failed.', [
                'file_id' => $fileId,
                'exception' => $exception,
            ]);
            throw new GoogleDriveStorageException(
                'Google Drive could not delete the material.',
                previous: $exception,
            );
        }
    }

    private function client(): Client
    {
        $client = new Client;
        $client->setApplicationName(config('app.name').' Learning Materials');
        $client->setScopes([Drive::DRIVE_FILE]);

        $credentialsJson = config('services.google_drive.credentials_json');
        if (filled($credentialsJson)) {
            $credentials = json_decode($credentialsJson, true);
            if (! is_array($credentials)) {
                throw new GoogleDriveStorageException('Google Drive credentials JSON is invalid.');
            }

            try {
                $client->setAuthConfig($credentials);
            } catch (Throwable $exception) {
                Log::error('Google Drive credentials could not be loaded.', ['exception' => $exception]);
                throw new GoogleDriveStorageException(
                    'Google Drive credentials are invalid.',
                    previous: $exception,
                );
            }

            return $client;
        }

        $credentialsPath = config('services.google_drive.credentials');
        if (! filled($credentialsPath) || ! is_file($credentialsPath) || ! is_readable($credentialsPath)) {
            throw new GoogleDriveStorageException(
                'Google Drive is not configured. Set GOOGLE_DRIVE_CREDENTIALS or GOOGLE_DRIVE_CREDENTIALS_JSON.',
            );
        }

        try {
            $client->setAuthConfig($credentialsPath);
        } catch (Throwable $exception) {
            Log::error('Google Drive credentials could not be loaded.', ['exception' => $exception]);
            throw new GoogleDriveStorageException(
                'Google Drive credentials are invalid.',
                previous: $exception,
            );
        }

        return $client;
    }
}
