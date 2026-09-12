<?php

namespace App\Modules\Accounts\Support;

use App\Modules\Accounts\Models\OfficialProfile;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

final class SkOfficialTermDates
{
    public const FIRST_START = '2023-01-01';

    /** SK Barangay Election Date under RA 11935 / RA 10742 / RA 11768 */
    public const ELECTION_DATE = '2023-10-30';

    public const SK_MIN_AGE = 18;

    public const SK_MAX_AGE = 30;

    public const MSG_UNDER_MIN_AGE = 'SK Official must be at least '.self::SK_MIN_AGE.' years old.';

    /**
     * Single reference date for SK age eligibility. The SK Reform Act
     * (RA 10742 / RA 11768) measures eligibility on election day, so the
     * configured election date — not the current date — drives the age window.
     */
    public static function eligibilityReference(?string $referenceDate = null): Carbon
    {
        return Carbon::parse($referenceDate ?: self::ELECTION_DATE)->startOfDay();
    }

    /**
     * Inclusive birthdate window for an SK official, derived from the reference date.
     *
     * Both edges are exact anniversaries, so a birthdate is allowed only when the
     * official is 18-30 years old on the reference date:
     *
     * - max (youngest allowed) = reference - 18 years; turning 18 exactly on the
     *   reference date qualifies, one day later does not.
     * - min (oldest allowed) = reference - 30 years; turning 30 exactly on the
     *   reference date qualifies, one day earlier is already past the 30-year mark.
     *
     * For the 2023-10-30 election this yields 1993-10-30 through 2005-10-30.
     *
     * @return array{min: string, max: string}
     */
    public static function birthdateBounds(?string $referenceDate = null): array
    {
        $reference = self::eligibilityReference($referenceDate);

        return [
            'min' => $reference->copy()->subYears(self::SK_MAX_AGE)->toDateString(),
            'max' => $reference->copy()->subYears(self::SK_MIN_AGE)->toDateString(),
        ];
    }

    /**
     * Birthday-aware age in completed years. Carbon counts whole years, so a
     * birthdate falling exactly on the reference date returns the full age.
     */
    public static function ageAt(CarbonInterface|string $dob, ?string $referenceDate = null): int
    {
        return (int) Carbon::parse($dob)->startOfDay()
            ->diffInYears(self::eligibilityReference($referenceDate));
    }

    public static function calculateAgeOnElection(CarbonInterface|string $dob, ?string $electionDate = null): int
    {
        return self::ageAt($dob, $electionDate);
    }

    public static function calculateCurrentAge(CarbonInterface|string $dob): int
    {
        return (int) Carbon::parse($dob)->startOfDay()->age;
    }

    public static function isEligibleElectionAge(CarbonInterface|string $dob, ?string $electionDate = null): bool
    {
        $bounds = self::birthdateBounds($electionDate);
        $dobString = Carbon::parse($dob)->startOfDay()->toDateString();

        return $dobString >= $bounds['min'] && $dobString <= $bounds['max'];
    }

    /**
     * Classify a birthdate against the allowed window.
     *
     * @return string one of 'ok', 'under', 'over'
     */
    public static function classifyBirthdate(CarbonInterface|string $dob, ?string $referenceDate = null): string
    {
        $bounds = self::birthdateBounds($referenceDate);
        $dobString = Carbon::parse($dob)->startOfDay()->toDateString();

        if ($dobString > $bounds['max']) {
            return 'under';
        }

        if ($dobString < $bounds['min']) {
            return 'over';
        }

        return 'ok';
    }

    public static function birthdateRuleMessage(?string $referenceDate = null): string
    {
        $bounds = self::birthdateBounds($referenceDate);

        return 'Birthdate must correspond to age '.self::SK_MIN_AGE.'–'.self::SK_MAX_AGE
            .' as of '.self::eligibilityReference($referenceDate)->format('F j, Y')
            .' (born between '.Carbon::parse($bounds['min'])->format('M j, Y')
            .' and '.Carbon::parse($bounds['max'])->format('M j, Y').').';
    }

    public static function overMaxAgeMessage(?string $referenceDate = null): string
    {
        return 'SK Official must not be older than '.self::SK_MAX_AGE.' years old as of '
            .self::eligibilityReference($referenceDate)->format('F j, Y').'. '
            .self::birthdateRuleMessage($referenceDate);
    }

    /**
     * Resolve the eligibility error for a birthdate, or null when it is allowed.
     * Shared by the create, update, and batch-import validators so the age rule
     * has a single implementation.
     */
    public static function birthdateErrorFor(CarbonInterface|string|null $dob, ?string $referenceDate = null): ?string
    {
        if ($dob === null || (is_string($dob) && trim($dob) === '')) {
            return null;
        }

        try {
            $classification = self::classifyBirthdate($dob, $referenceDate);
        } catch (\Throwable) {
            return null;
        }

        return match ($classification) {
            'under' => self::MSG_UNDER_MIN_AGE,
            'over' => self::overMaxAgeMessage($referenceDate),
            default => null,
        };
    }

    /** Default / maximum term length in years. */
    public const TERM_YEARS = 5;

    public const MIN_TERM_YEARS = 3;

    public const MAX_TERM_YEARS = 5;

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
        return Carbon::parse($start)->startOfDay()->addYears(self::MAX_TERM_YEARS);
    }

    public static function minTermEndForStart(CarbonInterface|string $start): Carbon
    {
        return Carbon::parse($start)->startOfDay()->addYears(self::MIN_TERM_YEARS);
    }

    public static function maxTermEndForStart(CarbonInterface|string $start): Carbon
    {
        return self::termEndForStart($start);
    }

    public static function maxStart(?CarbonInterface $asOf = null): string
    {
        return ($asOf ?? Carbon::now())->copy()->startOfDay()->addDays(self::START_ADVANCE_DAYS)->toDateString();
    }

    public static function currentStart(?CarbonInterface $asOf = null): string
    {
        return self::defaultStart($asOf);
    }

    /**
     * President term dates only (null when no SK Federation President is set).
     *
     * @return array{term_start: string, term_end: string}|null
     */
    public static function presidentTermDates(int $tenantId): ?array
    {
        if ($tenantId <= 0
            || ! Schema::hasTable('official_profiles')
            || ! Schema::hasColumn('official_profiles', 'federation_position')
            || ! Schema::hasTable('official_terms')
        ) {
            return null;
        }

        try {
            $president = OfficialProfile::query()
                ->where('federation_position', 'President')
                ->whereHas('user', function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                })
                ->with('latestTerm')
                ->first();

            $term = $president?->latestTerm;
            if ($term === null || empty($term->term_start) || empty($term->term_end)) {
                return null;
            }

            $start = Carbon::parse($term->term_start)->startOfDay()->toDateString();
            $end = Carbon::parse($term->term_end)->startOfDay()->toDateString();
        } catch (\Throwable) {
            return null;
        }

        if ($start < self::FIRST_START) {
            $start = self::FIRST_START;
        }

        if ($end <= $start) {
            $end = self::termEndForStart($start)->toDateString();
        }

        return ['term_start' => $start, 'term_end' => $end];
    }

    public static function hasPresident(int $tenantId): bool
    {
        return self::presidentTermDates($tenantId) !== null;
    }

    /**
     * Use President (then Vice President) official_terms so Manage Accounts
     * defaults match the current SK Federation leadership term window.
     *
     * @return array{term_start: string, term_end: string}
     */
    public static function leadershipDefaults(int $tenantId, ?CarbonInterface $asOf = null): array
    {
        $fallbackStart = self::defaultStart($asOf);
        $fallbackEnd = self::termEndForStart($fallbackStart)->toDateString();

        $presidentTerms = self::presidentTermDates($tenantId);
        if ($presidentTerms !== null) {
            return $presidentTerms;
        }

        if ($tenantId <= 0
            || ! Schema::hasTable('official_profiles')
            || ! Schema::hasColumn('official_profiles', 'federation_position')
            || ! Schema::hasTable('official_terms')
        ) {
            return ['term_start' => $fallbackStart, 'term_end' => $fallbackEnd];
        }

        try {
            $vicePresident = OfficialProfile::query()
                ->where('federation_position', 'Vice President')
                ->whereHas('user', function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                })
                ->with('latestTerm')
                ->first();

            $term = $vicePresident?->latestTerm;

            if ($term === null || empty($term->term_start) || empty($term->term_end)) {
                return ['term_start' => $fallbackStart, 'term_end' => $fallbackEnd];
            }

            $start = Carbon::parse($term->term_start)->startOfDay()->toDateString();
            $end = Carbon::parse($term->term_end)->startOfDay()->toDateString();
        } catch (\Throwable) {
            return ['term_start' => $fallbackStart, 'term_end' => $fallbackEnd];
        }

        if ($start < self::FIRST_START) {
            $start = self::FIRST_START;
        }

        if ($end <= $start) {
            $end = self::termEndForStart($start)->toDateString();
        }

        return ['term_start' => $start, 'term_end' => $end];
    }

    public static function startRuleMessage(): string
    {
        return 'Term start date must be on or after '.self::FIRST_START.' and at most '.self::START_ADVANCE_DAYS.' days in the future.';
    }

    public static function endRuleMessage(): string
    {
        return 'Term end date must be between '.self::MIN_TERM_YEARS.' and '.self::MAX_TERM_YEARS.' years after the term start date, or match the current federation leadership term.';
    }

    /**
     * @param  array{term_start?: string, term_end?: string}|null  $leadership
     * @return array<string, string>
     */
    public static function errorsFor(?string $start, ?string $end, ?CarbonInterface $asOf = null, ?array $leadership = null): array
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
        $leadershipStart = is_string($leadership['term_start'] ?? null) ? $leadership['term_start'] : null;

        // Leadership start may be historical; allow it when aligning everyone to that term.
        $startAllowed = ($startDate >= self::FIRST_START && $startDate <= $maxStart)
            || ($leadershipStart !== null && $startDate === $leadershipStart);

        if (! $startAllowed) {
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

        $leadershipEnd = is_string($leadership['term_end'] ?? null) ? $leadership['term_end'] : null;
        $matchesLeadership = $leadershipStart !== null
            && $leadershipEnd !== null
            && $startDate === $leadershipStart
            && $endDate === $leadershipEnd;

        if ($matchesLeadership) {
            return [];
        }

        $minEnd = self::minTermEndForStart($startDate)->toDateString();
        $maxEnd = self::maxTermEndForStart($startDate)->toDateString();

        if ($endDate < $minEnd || $endDate > $maxEnd) {
            return ['term_end' => self::endRuleMessage()];
        }

        return [];
    }
}
