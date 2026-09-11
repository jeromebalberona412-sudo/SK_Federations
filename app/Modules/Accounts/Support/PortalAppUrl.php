<?php

namespace App\Modules\Accounts\Support;

/**
 * Resolves and normalizes portal base URLs for activation / password-setup emails.
 *
 * Hostinger misconfig often sets SK_OFFICIALS_APP_URL without a scheme
 * (e.g. "skoneportal-com-739030.hostingersite.com"). Concatenating that into an
 * email href makes browsers treat the hostname as a path:
 * "/skoneportal-com-739030.hostingersite.com/activate-account/...".
 */
final class PortalAppUrl
{
    public static function officials(): ?string
    {
        return self::normalize(config('services.sk_officials_app_url'));
    }

    public static function federation(): ?string
    {
        return self::normalize(config('services.sk_fed_app_url') ?: config('app.url'));
    }

    public static function officialsOrFail(): string
    {
        $officials = self::officials();
        $federation = self::federation();

        if ($officials === null) {
            throw new \InvalidArgumentException(
                'SK_OFFICIALS_APP_URL is missing or empty. Set it to the full SK Officials URL including https://, then run php artisan optimize:clear.'
            );
        }

        if ($federation !== null && strcasecmp($officials, $federation) === 0) {
            throw new \InvalidArgumentException(
                'SK_OFFICIALS_APP_URL must not match the Federation portal URL. Use the SK Officials https:// URL, then run php artisan optimize:clear.'
            );
        }

        return $officials;
    }

    public static function federationOrFail(): string
    {
        $federation = self::federation();

        if ($federation === null) {
            throw new \InvalidArgumentException(
                'SK_FED_APP_URL / APP_URL is missing or empty. Set a full https:// URL, then run php artisan optimize:clear.'
            );
        }

        return $federation;
    }

    /**
     * Build an absolute portal path URL (scheme + host required).
     */
    public static function join(string $baseUrl, string $path, array $query = []): string
    {
        $base = self::normalize($baseUrl);

        if ($base === null) {
            throw new \InvalidArgumentException('Portal base URL must be an absolute http(s) URL.');
        }

        $path = '/'.ltrim($path, '/');
        $url = $base.$path;

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        return $url;
    }

    public static function normalize(mixed $url): ?string
    {
        $value = trim((string) $url);

        if ($value === '') {
            return null;
        }

        // Accidental leading slash turns the hostname into a path: /example.com/...
        $value = ltrim($value, '/');

        if (str_starts_with($value, '//')) {
            $value = 'https:'.$value;
        }

        if (! preg_match('#^https?://#i', $value)) {
            // Bare hostname from Hostinger .env (missing scheme).
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

        return $scheme.'://'.$host.$port.$path;
    }
}
