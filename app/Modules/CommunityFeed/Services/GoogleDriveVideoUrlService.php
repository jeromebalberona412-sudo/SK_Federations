<?php

namespace App\Modules\CommunityFeed\Services;

use Illuminate\Validation\ValidationException;

/**
 * Parse and validate Google Drive file share URLs for Community Feed video embeds.
 * Stores/returns metadata only — never downloads or proxies video bytes.
 */
class GoogleDriveVideoUrlService
{
    public const MEDIA_TYPE = 'google_drive_video';

    public const PROVIDER = 'google_drive';

    public const VALIDATION_MESSAGE = 'Please enter a valid Google Drive video link.';

    /**
     * @return array{
     *     media_type: string,
     *     media_url: string,
     *     external_provider: string,
     *     external_id: string,
     *     preview_url: string,
     *     open_url: string
     * }|null
     */
    public function parse(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if ($this->isDangerousScheme($url)) {
            return null;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! $this->isTrustedHost($host)) {
            return null;
        }

        $fileId = $this->extractFileId($parts);
        if ($fileId === null || ! $this->isValidFileId($fileId)) {
            return null;
        }

        $openUrl = 'https://drive.google.com/file/d/'.$fileId.'/view';
        $previewUrl = 'https://drive.google.com/file/d/'.$fileId.'/preview';

        return [
            'media_type' => self::MEDIA_TYPE,
            'media_url' => $openUrl,
            'external_provider' => self::PROVIDER,
            'external_id' => $fileId,
            'preview_url' => $previewUrl,
            'open_url' => $openUrl,
        ];
    }

    public function isGoogleDriveVideoUrl(?string $url): bool
    {
        return $this->parse($url) !== null;
    }

    public function looksLikeGoogleDriveHost(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '' || $this->isDangerousScheme($url)) {
            return false;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return $this->isTrustedHost($host);
    }

    /**
     * @throws ValidationException
     */
    public function normalizeForStorage(?string $url, bool $requireDriveVideo = false): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if ($this->isDangerousScheme($url)) {
            throw ValidationException::withMessages([
                'link_url' => [self::VALIDATION_MESSAGE],
            ]);
        }

        $drive = $this->parse($url);
        if ($drive !== null) {
            return $drive['media_url'];
        }

        if ($requireDriveVideo || $this->looksLikeGoogleDriveHost($url)) {
            throw ValidationException::withMessages([
                'link_url' => [self::VALIDATION_MESSAGE],
            ]);
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'link_url' => ['Please enter a valid link URL.'],
            ]);
        }

        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'link_url' => ['Please enter a valid link URL.'],
            ]);
        }

        if (mb_strlen($url) > 2048) {
            throw ValidationException::withMessages([
                'link_url' => ['Link is too long.'],
            ]);
        }

        return $url;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function present(?string $storedUrl): ?array
    {
        $parsed = $this->parse($storedUrl);
        if ($parsed === null) {
            return null;
        }

        return [
            'media_type' => $parsed['media_type'],
            'media_url' => $parsed['media_url'],
            'external_provider' => $parsed['external_provider'],
            'external_id' => $parsed['external_id'],
            'preview_url' => $parsed['preview_url'],
            'open_url' => $parsed['open_url'],
        ];
    }

    private function isDangerousScheme(string $url): bool
    {
        $lower = strtolower(ltrim($url));

        return str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'data:')
            || str_starts_with($lower, 'blob:')
            || str_starts_with($lower, 'vbscript:');
    }

    private function isTrustedHost(string $host): bool
    {
        return $host === 'drive.google.com'
            || $host === 'docs.google.com'
            || str_ends_with($host, '.drive.google.com')
            || str_ends_with($host, '.docs.google.com');
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function extractFileId(array $parts): ?string
    {
        $path = (string) ($parts['path'] ?? '');
        $query = [];
        if (! empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $path, $m)) {
            return $m[1];
        }

        if (preg_match('#/uc$#', $path) || preg_match('#/open$#', $path) || $path === '/uc' || $path === '/open') {
            $id = $query['id'] ?? null;

            return is_string($id) && $id !== '' ? $id : null;
        }

        if (isset($query['id']) && is_string($query['id']) && $query['id'] !== '') {
            if ($path === '/' || $path === '' || $path === '/open' || $path === '/uc') {
                return $query['id'];
            }
        }

        return null;
    }

    private function isValidFileId(string $fileId): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_-]{10,128}$/', $fileId);
    }
}
