<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived server page/list cache for SK Federations sidebar pages.
 * Bump a bucket after writes so the next read rebuilds fresh data.
 */
class SkFedPageCache
{
    public const TTL_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $parts
     */
    public function remember(string $bucket, callable $callback, array $parts = [], int $ttl = self::TTL_SECONDS): mixed
    {
        try {
            return Cache::remember($this->payloadKey($bucket, $parts), $ttl, $callback);
        } catch (\Throwable $e) {
            report($e);

            return $callback();
        }
    }

    public function bump(string $bucket): void
    {
        $versionKey = $this->versionKey($bucket);
        Cache::forever($versionKey, ((int) Cache::get($versionKey, 1)) + 1);
    }

    /**
     * @param  list<string>  $buckets
     */
    public function bumpMany(array $buckets): void
    {
        foreach ($buckets as $bucket) {
            $this->bump($bucket);
        }
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function payloadKey(string $bucket, array $parts): string
    {
        $version = (int) Cache::get($this->versionKey($bucket), 1);

        return sprintf(
            'skfed:page:%s:v%d:%s',
            $bucket,
            $version,
            md5((string) json_encode($parts))
        );
    }

    private function versionKey(string $bucket): string
    {
        return sprintf('skfed:pagever:%s', $bucket);
    }
}
