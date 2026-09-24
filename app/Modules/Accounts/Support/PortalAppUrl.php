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
    /**
     * Canonical SK Officials portal (replaces retired Hostinger provisional hosts).
     */
    public const CANONICAL_OFFICIALS_URL = 'https://skofficials.skoneportal.com';

    public static function officials(): ?string
    {
        $url = self::preferAbsolute([
            config('services.sk_officials_app_url'),
        ]);

        if ($url !== null && self::isDeprecatedOfficialsHost($url)) {
            return self::CANONICAL_OFFICIALS_URL;
        }

        // Hostinger / missing env: never leave Officials activation emails without a portal base.
        if ($url === null) {
            return self::CANONICAL_OFFICIALS_URL;
        }

        return $url;
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
        // Prefer configured public portal URLs for emails/share links.
        // Include the current request host so password-reset / verify emails still
        // work when APP_URL / SK_FED_APP_URL are missing or still set to localhost.
        $candidates = [
            config('app.public_url'),
            config('services.sk_fed_app_url'),
            config('app.url'),
            self::currentRequestBase(),
        ];

        $preferred = self::preferAbsolute($candidates);
        if ($preferred !== null) {
            return $preferred;
        }

        if (app()->environment('local', 'testing')) {
            $local = self::normalize(config('app.url'))
                ?? self::normalize(config('services.sk_fed_app_url'))
                ?? self::normalize(self::currentRequestBase());

            if ($local !== null) {
                return $local;
            }

            return rtrim((string) config('app.url'), '/');
        }

        return '';
    }

    /**
     * Public scheme+host from the active HTTP request, when available.
     */
    private static function currentRequestBase(): ?string
    {
        try {
            if (! app()->bound('request')) {
                return null;
            }

            $request = request();
            $host = strtolower(trim((string) $request->getHost()));
            if ($host === '') {
                return null;
            }

            return $request->getSchemeAndHttpHost();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Use $url when it is a real public host; otherwise this app's public base.
     * Production never falls back to localhost.
     */
    public static function publicBase(?string $url = null): string
    {
        if (is_string($url) && self::isDeprecatedOfficialsHost($url)) {
            $url = self::CANONICAL_OFFICIALS_URL;
        }

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
     * Retired Hostinger provisional Officials hosts must not appear in activation emails.
     */
    public static function isDeprecatedOfficialsHost(?string $url): bool
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        $host = trim($host, '[]');

        if ($host === '') {
            return false;
        }

        return str_contains($host, 'hostingersite.com')
            || str_contains($host, 'skoneportal-com-739030');
    }

    /**
     * Build an absolute route URL from this app's canonical public base.
     */
    public static function absoluteRoute(string $name, array $parameters = []): string
    {
        $path = route($name, $parameters, absolute: false);
        $base = self::selfApp();

        if ($base === '') {
            $base = self::preferAbsolute([
                self::currentRequestBase(),
                config('services.sk_fed_app_url'),
                config('app.public_url'),
                config('app.url'),
            ]) ?? '';
        }

        if ($base === '') {
            $base = self::federationOrFail();
        }

        return self::join($base, $path);
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
