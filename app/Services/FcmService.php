<?php

namespace App\Services;

use App\Models\User;
use Google\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FcmService
{
    /**
     * Send one notification to every registered installation for a student.
     *
     * @param array<string, mixed> $data
     */
    public function send(User $user, array $data, ?string $notificationId = null): void
    {
        $devices = $user->fcmDeviceTokens()->get();

        if ($devices->isEmpty()) {
            return;
        }

        $projectId = config('services.firebase.project_id');
        $credentials = $this->credentials();

        if (! $projectId || ! $credentials) {
            Log::warning('FCM delivery skipped because Firebase credentials are not configured.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $accessToken = $this->accessToken($projectId, $credentials);
        $endpoint = 'https://fcm.googleapis.com/v1/projects/'.rawurlencode($projectId).'/messages:send';

        foreach ($devices as $device) {
            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->timeout(15)
                    ->post($endpoint, [
                        'message' => [
                            'token' => $device->token,
                            'notification' => [
                                'title' => (string) ($data['title'] ?? 'EduSync'),
                                'body' => (string) ($data['message'] ?? ''),
                            ],
                            'data' => $this->stringData([
                                ...$data,
                                'notification_id' => $notificationId ?? '',
                                'url' => route('student.notifications'),
                            ]),
                            'webpush' => [
                                'fcm_options' => [
                                    'link' => route('student.notifications'),
                                ],
                            ],
                        ],
                    ]);
            } catch (ConnectionException $exception) {
                Log::error('Firebase could not be reached for a push notification.', [
                    'user_id' => $user->id,
                    'device_token_id' => $device->id,
                    'exception' => $exception,
                ]);

                continue;
            }

            if ($response->successful()) {
                $device->forceFill(['last_used_at' => now()])->save();

                continue;
            }

            if ($this->isInvalidTokenResponse($response->json())) {
                $device->delete();
                Log::info('Removed an invalid FCM device token.', [
                    'user_id' => $user->id,
                    'device_token_id' => $device->id,
                    'http_status' => $response->status(),
                ]);

                continue;
            }

            Log::error('Firebase rejected a push notification.', [
                'user_id' => $user->id,
                'device_token_id' => $device->id,
                'http_status' => $response->status(),
                'response' => $response->json(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        $json = config('services.firebase.credentials_json');
        if (filled($json)) {
            $credentials = json_decode($json, true);
            if (! is_array($credentials)) {
                throw new RuntimeException('FIREBASE_CREDENTIALS_JSON must contain valid service-account JSON.');
            }

            return $credentials;
        }

        $path = config('services.firebase.credentials');
        if (! filled($path)) {
            return null;
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The configured Firebase service-account file is missing or unreadable.');
        }

        $credentials = json_decode((string) file_get_contents($path), true);
        if (! is_array($credentials)) {
            throw new RuntimeException('The configured Firebase service-account file does not contain valid JSON.');
        }

        return $credentials;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function accessToken(string $projectId, array $credentials): string
    {
        return Cache::remember(
            'firebase.fcm.access-token.'.hash('sha256', $projectId),
            now()->addMinutes(50),
            function () use ($credentials): string {
                $client = new Client;
                $client->setAuthConfig($credentials);
                $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
                $response = $client->fetchAccessTokenWithAssertion();
                $accessToken = $response['access_token'] ?? null;

                if (! is_string($accessToken) || $accessToken === '') {
                    throw new RuntimeException('Google did not return an access token for Firebase Cloud Messaging.');
                }

                return $accessToken;
            },
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function stringData(array $data): array
    {
        $strings = [];
        foreach ($data as $key => $value) {
            $strings[(string) $key] = is_scalar($value)
                ? (string) $value
                : json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $strings;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function isInvalidTokenResponse(?array $body): bool
    {
        foreach ($body['error']['details'] ?? [] as $detail) {
            if (($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        $message = strtolower((string) ($body['error']['message'] ?? ''));

        return str_contains($message, 'registration token is not a valid fcm registration token')
            || str_contains($message, 'requested entity was not found')
                && str_contains($message, 'token');
    }
}
