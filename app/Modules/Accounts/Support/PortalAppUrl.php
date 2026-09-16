<?php

namespace App\Modules\Accounts\Support;

use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\URL;

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
        return self::preferAbsolute([
            config('services.sk_officials_app_url'),
        ]);
    }

    public static function federation(): ?string
    {
        return self::preferAbsolute([
            config('services.sk_fed_app_url'),
            config('app.public_url'),
            config('app.url'),
        ]);
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

    public static function kabataan(): string
    {
        return self::preferAbsolute([
            config('services.kabataan_app_url'),
        ]) ?? '';
    }

    /**
     * This Federation app's public base URL (share links, same-app absolute URLs).
     */
    public static function selfApp(): string
    {
        // Emails and share links must use configured APP_URL / public portal URLs,
        // never the current request host (localhost, 127.0.0.1, or LAN IPs).
        $candidates = [
            config('app.public_url'),
            config('services.sk_fed_app_url'),
            config('app.url'),
        ];

        $preferred = self::preferAbsolute($candidates);
        if ($preferred !== null) {
            return $preferred;
        }

        if (app()->environment('local', 'testing')) {
            return rtrim((string) config('app.url'), '/');
        }

        return '';
    }

    /**
     * Use $url when it is a real public host; otherwise this app's public base.
     * Production never falls back to localhost.
     */
    public static function publicBase(?string $url = null): string
    {
        $self = self::selfApp();

        return self::preferAbsolute([$url, $self]) ?? $self;
    }

    /**
     * Keep a public absolute link as-is. Rewrite localhost/127.0.0.1/LAN IPs to APP_URL.
     */
    public static function mailLink(string $url): string
    {
        $value = trim($url);
        if ($value === '') {
            return self::selfApp();
        }

        if (! preg_match('#^https?://#i', $value) && ! str_starts_with($value, '//')) {
            $base = self::selfApp();

            return $base === '' ? '/'.ltrim($value, '/') : self::join($base, $value);
        }

        if (! self::isNonPublic($value)) {
            return $value;
        }

        $public = self::selfApp();
        if ($public === '' || self::isNonPublic($public)) {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $query = parse_url($value, PHP_URL_QUERY);
        $suffix = is_string($query) && $query !== '' ? $path.'?'.$query : $path;

        return self::join($public, $suffix);
    }

    public static function isLoopback(?string $url): bool
    {
        return self::isNonPublic($url);
    }

    /**
     * True for localhost, loopback, and private/LAN hosts that must not appear in emails.
     */
    public static function isNonPublic(?string $url): bool
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        $host = trim($host, '[]');

        if ($host === ''
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || $host === '0.0.0.0'
            || str_ends_with($host, '.localhost')
        ) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }

    /**
     * Build an absolute route URL from this app's canonical public base.
     */
    public static function absoluteRoute(string $name, array $parameters = []): string
    {
        $path = route($name, $parameters, absolute: false);

        return self::join(self::selfApp(), $path);
    }

    public static function temporarySignedRoute(
        string $name,
        DateTimeInterface|DateInterval|int $expiration,
        array $parameters = [],
    ): string {
        return self::withPublicRoot(
            fn (): string => URL::temporarySignedRoute($name, $expiration, $parameters, absolute: true)
        );
    }

    /**
     * Prefer a non-localhost absolute URL. Loopback is allowed only in local/testing.
     *
     * @param  list<mixed>  $candidates
     */
    public static function preferAbsolute(array $candidates): ?string
    {
        $normalized = [];

        foreach ($candidates as $candidate) {
            $url = self::normalize($candidate);
            if ($url !== null) {
                $normalized[] = $url;
            }
        }

        foreach ($normalized as $url) {
            if (! self::isNonPublic($url)) {
                return $url;
            }
        }

        // Never leak localhost/127.0.0.1/LAN IPs into production emails or share links.
        if (app()->environment('local', 'testing') && $normalized !== []) {
            return $normalized[0];
        }

        return null;
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

    private static function withPublicRoot(callable $callback): string
    {
        $appRoot = rtrim((string) config('app.url'), '/');
        $publicRoot = self::selfApp();

        if ($publicRoot === '' || ! filter_var($publicRoot, FILTER_VALIDATE_URL)) {
            return (string) $callback();
        }

        URL::forceRootUrl($publicRoot);
        self::applySchemeFromUrl($publicRoot);

        try {
            return (string) $callback();
        } finally {
            $restore = filter_var($appRoot, FILTER_VALIDATE_URL) ? $appRoot : $publicRoot;
            URL::forceRootUrl($restore);
            self::applySchemeFromUrl($restore);
        }
    }

    private static function applySchemeFromUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme === 'https') {
            URL::forceScheme('https');
        } elseif ($scheme === 'http') {
            URL::forceScheme('http');
        }
    }
}
