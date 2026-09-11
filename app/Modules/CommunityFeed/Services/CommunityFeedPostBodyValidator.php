<?php

namespace App\Modules\CommunityFeed\Services;

use App\Services\ProhibitedWordsService;
use Illuminate\Validation\ValidationException;

class CommunityFeedPostBodyValidator
{
    public const MIN_TOTAL_CHARS = 3;

    public const MAX_TOTAL_CHARS = 25000;

    public const CHARS_PER_LINE = 36;

    public function __construct(
        private readonly ProhibitedWordsService $prohibitedWords,
    ) {}

    public function normalize(string $body): string
    {
        return str_replace(["\r\n", "\r"], "\n", $body);
    }

    public function countedLength(string $normalizedBody, ?int $charsPerLine = null): int
    {
        $cpl = max(8, $charsPerLine ?? self::CHARS_PER_LINE);
        $lines = explode("\n", $normalizedBody);
        $total = 0;
        $lastIndex = count($lines) - 1;

        foreach ($lines as $index => $line) {
            $len = mb_strlen($line);
            if ($index < $lastIndex) {
                $total += max($cpl, $len);
            } else {
                $total += $len;
            }
        }

        return $total;
    }

    /**
     * @throws ValidationException
     */
    public function assertValid(string $body): string
    {
        $normalized = $this->normalize($body);

        if ($normalized === '' || preg_match('/\S/u', $normalized) !== 1) {
            throw ValidationException::withMessages([
                'body' => 'Please write something. Posts cannot be empty or only spaces.',
            ]);
        }

        if (mb_strlen(trim($normalized)) < self::MIN_TOTAL_CHARS) {
            throw ValidationException::withMessages([
                'body' => 'Please write at least 3 characters before posting.',
            ]);
        }

        if ($this->countedLength($normalized) > self::MAX_TOTAL_CHARS) {
            throw ValidationException::withMessages([
                'body' => 'The post content cannot exceed 25,000 characters (new lines count as a full line).',
            ]);
        }

        $this->prohibitedWords->assertClean($normalized, 'body');

        return $normalized;
    }
}
