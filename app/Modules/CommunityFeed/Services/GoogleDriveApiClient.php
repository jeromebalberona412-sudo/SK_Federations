<?php

namespace App\Modules\CommunityFeed\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Server-side Google Drive API client (metadata only — never deletes Drive files).
 */
class GoogleDriveApiClient
{
    public const SCOPE_READONLY = 'https://www.googleapis.com/auth/drive.readonly';

    /**
     * @return array{id: string, name: string|null}
     */
    public function getFolder(string $folderId): array
    {
        $response = $this->request('GET', 'https://www.googleapis.com/drive/v3/files/'.rawurlencode($folderId), [
            'fields' => 'id,name,mimeType,trashed',
            'supportsAllDrives' => 'true',
        ]);

        $mime = (string) ($response['mimeType'] ?? '');
        if ($mime !== 'application/vnd.google-apps.folder') {
            throw new RuntimeException('The Google Drive URL is not a folder.');
        }
        if (! empty($response['trashed'])) {
            throw new RuntimeException('This Google Drive folder is not accessible.');
        }

        return [
            'id' => (string) ($response['id'] ?? $folderId),
            'name' => isset($response['name']) ? (string) $response['name'] : null,
        ];
    }

    /**
     * List video files directly inside a folder (paginated).
     * Caller must still validate mimeType and select oldest N.
     *
     * @return list<array{id: string, name: string, mimeType: string, createdTime: string, size: int|null, webViewLink: string|null}>
     */
    public function listVideoFilesInFolder(string $folderId): array
    {
        $files = [];
        $pageToken = null;
        $escapedFolderId = $this->escapeDriveQueryValue($folderId);
        $maxVideos = CommunityFeedGoogleDriveFolderSyncService::maxVideos();

        do {
            $query = sprintf(
                "'%s' in parents and trashed = false and mimeType contains 'video/'",
                $escapedFolderId
            );

            $params = [
                'q' => $query,
                'fields' => 'nextPageToken,files(id,name,mimeType,createdTime,size,webViewLink)',
                'orderBy' => 'createdTime',
                'pageSize' => 100,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'spaces' => 'drive',
            ];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $payload = $this->request('GET', 'https://www.googleapis.com/drive/v3/files', $params);
            $batch = is_array($payload['files'] ?? null) ? $payload['files'] : [];

            foreach ($batch as $file) {
                if (! is_array($file)) {
                    continue;
                }
                $id = trim((string) ($file['id'] ?? ''));
                $mime = trim((string) ($file['mimeType'] ?? ''));
                $created = trim((string) ($file['createdTime'] ?? ''));
                if ($id === '' || $mime === '' || $created === '') {
                    continue;
                }

                $files[] = [
                    'id' => $id,
                    'name' => (string) ($file['name'] ?? $id),
                    'mimeType' => $mime,
                    'createdTime' => $created,
                    'size' => isset($file['size']) ? (int) $file['size'] : null,
                    'webViewLink' => isset($file['webViewLink']) ? (string) $file['webViewLink'] : null,
                ];
            }

            $validCount = 0;
            foreach ($files as $row) {
                if (str_starts_with(strtolower($row['mimeType']), 'video/')) {
                    $validCount++;
                }
            }
            if ($validCount >= $maxVideos) {
                break;
            }

            $pageToken = isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null;
        } while ($pageToken);

        return $files;
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $query = []): array
    {
        $query = array_filter(
            $query,
            static fn ($value) => $value !== null && $value !== ''
        );

        $token = $this->accessToken();
        $pending = Http::timeout(45)->acceptJson();

        if ($token !== null) {
            $pending = $pending->withToken($token);
        } else {
            $apiKey = trim((string) config('services.google_drive.api_key', ''));
            if ($apiKey === '') {
                throw new RuntimeException(
                    'Google Drive API is not configured. Set GOOGLE_DRIVE_API_KEY or GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON.'
                );
            }
            $query['key'] = $apiKey;
        }

        try {
            $response = $pending->get($url, $query);
        } catch (Throwable $e) {
            throw new RuntimeException('Google Drive API timeout or network error. Please try again.', 0, $e);
        }

        if ($response->status() === 404) {
            throw new RuntimeException('Google Drive folder does not exist or is inaccessible.');
        }
        if ($response->status() === 403) {
            throw new RuntimeException('Permission denied for this Google Drive folder. Share it with the configured service account or make it accessible.');
        }
        if ($response->status() === 429) {
            throw new RuntimeException('Google Drive API rate limit reached. Please try again shortly.');
        }
        if ($response->failed()) {
            $message = (string) ($response->json('error.message') ?? 'Google Drive API error.');
            throw new RuntimeException($message);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Unexpected Google Drive API response.');
        }

        return $json;
    }

    private function accessToken(): ?string
    {
        $json = $this->serviceAccountPayload();
        if ($json === null) {
            return null;
        }

        $cacheKey = 'google_drive_sa_token_'.sha1((string) ($json['client_email'] ?? 'sa'));

        return Cache::remember($cacheKey, 3000, function () use ($json) {
            $jwt = $this->buildServiceAccountJwt($json);
            try {
                $response = Http::asForm()
                    ->timeout(30)
                    ->post('https://oauth2.googleapis.com/token', [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $jwt,
                    ]);
            } catch (Throwable $e) {
                throw new RuntimeException('Unable to authenticate with Google Drive.', 0, $e);
            }

            if ($response->failed()) {
                throw new RuntimeException('Unable to authenticate with Google Drive service account.');
            }

            $token = (string) ($response->json('access_token') ?? '');
            if ($token === '') {
                throw new RuntimeException('Google Drive access token missing.');
            }

            return $token;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serviceAccountPayload(): ?array
    {
        $raw = trim((string) config('services.google_drive.service_account_json', ''));
        if ($raw === '') {
            return null;
        }

        $path = $raw;
        if (! is_file($path)) {
            $relative = base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($raw, '/\\')));
            if (is_file($relative)) {
                $path = $relative;
            }
        }

        if (is_file($path)) {
            $raw = (string) file_get_contents($path);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            if (is_file($path) || str_ends_with(strtolower($path), '.json')) {
                throw new RuntimeException('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON is invalid or unreadable.');
            }

            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $sa
     */
    private function buildServiceAccountJwt(array $sa): string
    {
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $sa['client_email'],
            'scope' => self::SCOPE_READONLY,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header.'.'.$claims;
        $privateKey = openssl_pkey_get_private((string) $sa['private_key']);
        if ($privateKey === false) {
            throw new RuntimeException('Invalid Google Drive service account private key.');
        }

        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new RuntimeException('Unable to sign Google Drive service account JWT.');
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function escapeDriveQueryValue(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
