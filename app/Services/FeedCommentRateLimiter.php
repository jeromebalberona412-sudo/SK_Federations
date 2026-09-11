<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Burst rate limiter for Community Feed comments/replies.
 * 3 actions inside WINDOW_SECONDS → COOLDOWN_SECONDS lockout.
 * Waiting longer than WINDOW_SECONDS since the last action resets the burst count.
 */
class FeedCommentRateLimiter
{
    public const BURST_LIMIT = 3;

    public const WINDOW_SECONDS = 60;

    public const COOLDOWN_SECONDS = 60;

    public const MAX_BODY_LENGTH = 2000;

    public const SCOPE_COMMENT = 'feed_comment';

    /**
     * @return array{
     *     allowed: bool,
     *     retry_after: int|null,
     *     message: string|null,
     *     burst_used: int,
     *     burst_remaining: int,
     *     locked: bool
     * }
     */
    public function check(string $userType, int $userId, string $scope = self::SCOPE_COMMENT): array
    {
        return $this->status($this->readState($userType, $userId, $scope), $scope);
    }

    /**
     * @return array{
     *     allowed: bool,
     *     retry_after: int|null,
     *     message: string|null,
     *     burst_used: int,
     *     burst_remaining: int,
     *     locked: bool
     * }
     */
    public function hit(string $userType, int $userId, string $scope = self::SCOPE_COMMENT): array
    {
        $now = time();
        $state = $this->readState($userType, $userId, $scope);

        if (($state['locked_until'] ?? 0) > $now) {
            return $this->status($state, $scope);
        }

        $count = (int) ($state['count'] ?? 0);
        $lastAt = (int) ($state['last_at'] ?? 0);

        if ($count > 0 && ($now - $lastAt) > self::WINDOW_SECONDS) {
            $count = 0;
        }

        $count++;

        if ($count >= self::BURST_LIMIT) {
            $state = [
                'count' => 0,
                'last_at' => $now,
                'locked_until' => $now + self::COOLDOWN_SECONDS,
            ];
        } else {
            $state = [
                'count' => $count,
                'last_at' => $now,
                'locked_until' => 0,
            ];
        }

        $this->writeState($userType, $userId, $state, $scope);

        $status = $this->status($state, $scope);
        if ($status['locked']) {
            $status['message'] = 'Comment limit reached (3 comments). Please wait '
                .$status['retry_after'].' second(s) before commenting again.';
            $status['burst_used'] = self::BURST_LIMIT;
            $status['burst_remaining'] = 0;
        }

        return $status;
    }

    /**
     * @param  array{count?: int, last_at?: int, locked_until?: int}  $state
     * @return array{
     *     allowed: bool,
     *     retry_after: int|null,
     *     message: string|null,
     *     burst_used: int,
     *     burst_remaining: int,
     *     locked: bool
     * }
     */
    private function status(array $state, string $scope = self::SCOPE_COMMENT): array
    {
        $now = time();
        $lockedUntil = (int) ($state['locked_until'] ?? 0);

        if ($lockedUntil > $now) {
            $retryAfter = max(1, $lockedUntil - $now);

            return [
                'allowed' => false,
                'retry_after' => $retryAfter,
                'message' => 'Too many comments. Please wait '.$retryAfter.' second(s) before commenting again.',
                'burst_used' => self::BURST_LIMIT,
                'burst_remaining' => 0,
                'locked' => true,
            ];
        }

        $count = (int) ($state['count'] ?? 0);
        $lastAt = (int) ($state['last_at'] ?? 0);

        if ($count > 0 && ($now - $lastAt) > self::WINDOW_SECONDS) {
            $count = 0;
        }

        return [
            'allowed' => true,
            'retry_after' => null,
            'message' => null,
            'burst_used' => $count,
            'burst_remaining' => max(0, self::BURST_LIMIT - $count),
            'locked' => false,
        ];
    }

    /**
     * @return array{count: int, last_at: int, locked_until: int}
     */
    private function readState(string $userType, int $userId, string $scope): array
    {
        $raw = Cache::get($this->cacheKey($userType, $userId, $scope));

        if (! is_array($raw)) {
            return ['count' => 0, 'last_at' => 0, 'locked_until' => 0];
        }

        return [
            'count' => (int) ($raw['count'] ?? 0),
            'last_at' => (int) ($raw['last_at'] ?? 0),
            'locked_until' => (int) ($raw['locked_until'] ?? 0),
        ];
    }

    /**
     * @param  array{count: int, last_at: int, locked_until: int}  $state
     */
    private function writeState(string $userType, int $userId, array $state, string $scope): void
    {
        $ttl = self::WINDOW_SECONDS + self::COOLDOWN_SECONDS + 5;
        Cache::put($this->cacheKey($userType, $userId, $scope), $state, $ttl);
    }

    private function cacheKey(string $userType, int $userId, string $scope): string
    {
        return self::SCOPE_COMMENT.'_burst:'.$userType.':'.$userId;
    }
}
