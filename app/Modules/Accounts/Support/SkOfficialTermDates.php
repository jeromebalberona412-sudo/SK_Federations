<?php

namespace App\Modules\Accounts\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class SkOfficialTermDates
{
    public const FIRST_START = '2023-01-01';

    public const TERM_YEARS = 5;

    public const START_ADVANCE_DAYS = 30;

    public static function defaultStart(?CarbonInterface $asOf = null): string
    {
        return ($asOf ?? Carbon::now())->copy()->startOfDay()->toDateString();
    }

    public static function defaultEnd(?CarbonInterface $asOf = null): string
    {
        return self::termEndForStart(self::defaultStart($asOf))->toDateString();
    }

    public static function termEndForStart(CarbonInterface|string $start): Carbon
    {
        return Carbon::parse($start)->startOfDay()->addYears(self::TERM_YEARS);
    }

    public static function maxStart(?CarbonInterface $asOf = null): string
    {
        return ($asOf ?? Carbon::now())->copy()->startOfDay()->addDays(self::START_ADVANCE_DAYS)->toDateString();
    }

    public static function currentStart(?CarbonInterface $asOf = null): string
    {
        return self::defaultStart($asOf);
    }

    public static function startRuleMessage(): string
    {
        return 'Term start date must be on or after '.self::FIRST_START.' and at most '.self::START_ADVANCE_DAYS.' days in the future.';
    }

    public static function endRuleMessage(): string
    {
        return 'Term end date must be exactly '.self::TERM_YEARS.' years after the term start date.';
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

        $asOfDay = ($asOf ?? Carbon::now())->copy()->startOfDay();
        $maxStart = self::maxStart($asOfDay);

        if ($startDate < self::FIRST_START || $startDate > $maxStart) {
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

        $requiredEnd = self::termEndForStart($startDate)->toDateString();
        if ($endDate !== $requiredEnd) {
            return ['term_end' => self::endRuleMessage()];
        }

        return [];
    }
}
