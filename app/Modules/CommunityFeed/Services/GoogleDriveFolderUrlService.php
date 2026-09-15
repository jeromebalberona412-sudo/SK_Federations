<?php

namespace App\Modules\CommunityFeed\Services;

use Illuminate\Validation\ValidationException;

/**
 * Parse and validate Google Drive folder share URLs for Community Feed sync.
 */
class GoogleDriveFolderUrlService
{
    public const VALIDATION_MESSAGE = 'Please enter a valid Google Drive folder URL.';

    /**
     * @return array{folder_id: string, folder_url: string}|null
     */
    public function parse(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '' || $this->isDangerousScheme($url)) {
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

        $path = (string) ($parts['path'] ?? '');
        if (preg_match('#/file/d/#', $path)) {
            return null;
        }

        $folderId = $this->extractFolderId($parts);
        if ($folderId === null || ! $this->isValidFolderId($folderId)) {
            return null;
        }

        return [
            'folder_id' => $folderId,
            'folder_url' => 'https://drive.google.com/drive/folders/'.$folderId,
        ];
    }

    /**
     * @return array{folder_id: string, folder_url: string}
     *
     * @throws ValidationException
     */
    public function parseOrFail(?string $url): array
    {
        $parsed = $this->parse($url);
        if ($parsed === null) {
            throw ValidationException::withMessages([
                'folder_url' => [self::VALIDATION_MESSAGE],
            ]);
        }

        return $parsed;
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
            || str_ends_with($host, '.drive.google.com');
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function extractFolderId(array $parts): ?string
    {
        $path = (string) ($parts['path'] ?? '');
        $query = [];
        if (! empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $path, $m)) {
            return $m[1];
        }

        if (isset($query['id']) && is_string($query['id']) && str_contains($path, 'folders')) {
            return $query['id'];
        }

        return null;
    }

    private function isValidFolderId(string $folderId): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_-]{10,128}$/', $folderId);
    }
}
