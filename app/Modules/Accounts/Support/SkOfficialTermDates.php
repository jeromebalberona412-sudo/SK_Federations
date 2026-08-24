<?php

namespace App\Modules\Accounts\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class SkOfficialTermDates
{
    public const FIRST_START = '2023-11-30';

    public const TERM_YEARS = 3;
    public const MAX_TERM_YEARS = 4;

    public const MONTH = 11;

    public const DAY = 30;

    public static function firstStart(): Carbon
    {
        return Carbon::create(2023, self::MONTH, self::DAY)->startOfDay();
    }

    public static function termEndForStart(CarbonInterface|string $start): Carbon
    {
        return Carbon::parse($start)->startOfDay()->addYears(self::TERM_YEARS);
    }

    /**
     * SK term starts every 3 years on November 30, beginning 2023-11-30 (RA 11935).
     *
     * @return list<string>
     */
    public static function allowedStartDates(?CarbonInterface $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $dates = [];
        $cursor = self::firstStart();

        while ($cursor->lte($asOf)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->copy()->addYears(self::TERM_YEARS);
        }

        return $dates;
    }

    public static function currentStart(?CarbonInterface $asOf = null): string
    {
        $allowed = self::allowedStartDates($asOf);

        if ($allowed === []) {
            return self::FIRST_START;
        }

        return (string) $allowed[array_key_last($allowed)];
    }

    public static function isValidStart(string $date, ?CarbonInterface $asOf = null): bool
    {
        return in_array($date, self::allowedStartDates($asOf), true);
    }

    public static function isValidPair(string $start, string $end, ?CarbonInterface $asOf = null): bool
    {
        if (! self::isValidStart($start, $asOf)) {
            return false;
        }

        return $end === self::termEndForStart($start)->toDateString();
    }

    public static function startRuleMessage(): string
    {
        return 'Term start must be November 30 of an SK term year (2023, 2026, 2029, …) under RA 11935. December 1 is not the legal commencement.';
    }

    public static function endRuleMessage(): string
    {
        return 'Term end must be between 3 and 4 years after the term start date.';
    }

    /**
     * @return array<string, string>
     */
    public static function errorsFor(?string $start, ?string $end, ?CarbonInterface $asOf = null): array
    {
        if (! is_string($start) || $start === '') {
            return [];
        }

        try {
            $startDate = Carbon::parse($start)->startOfDay()->toDateString();
        } catch (\Throwable) {
            return [];
        }

        if (! self::isValidStart($startDate, $asOf)) {
            return ['term_start' => self::startRuleMessage()];
        }

        if (! is_string($end) || $end === '') {
            return [];
        }

        try {
            $endDate = Carbon::parse($end)->startOfDay()->toDateString();
        } catch (\Throwable) {
            return [];
        }

        $minimumEnd = self::termEndForStart($startDate)->toDateString();
        $maximumEnd = Carbon::parse($startDate)->startOfDay()->addYears(self::MAX_TERM_YEARS)->toDateString();

        if ($endDate < $minimumEnd || $endDate > $maximumEnd) {
            return ['term_end' => self::endRuleMessage()];
        }

        return [];
    }
}
