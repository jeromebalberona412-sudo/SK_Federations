<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class FeedCommentRateLimiter
{
    public const COOLDOWN_SECONDS = 60;

    public const MAX_BODY_LENGTH = 500;

    /**
     * @return array{allowed: bool, retry_after: int|null, message: string|null}
     */
    public function check(string $userType, int $userId): array
    {
        $key = $this->cacheKey($userType, $userId);

        if (! Cache::has($key)) {
            return ['allowed' => true, 'retry_after' => null, 'message' => null];
        }

        $retryAfter = max(1, (int) Cache::get($key) - time());

        return [
            'allowed' => false,
            'retry_after' => $retryAfter,
            'message' => "Please wait {$retryAfter} second(s) before commenting again.",
        ];
    }

    public function hit(string $userType, int $userId): void
    {
        $key = $this->cacheKey($userType, $userId);
        Cache::put($key, time() + self::COOLDOWN_SECONDS, self::COOLDOWN_SECONDS);
    }

    private function cacheKey(string $userType, int $userId): string
    {
        return 'feed_comment_cooldown:'.$userType.':'.$userId;
    }
}
