<?php

namespace App\Modules\Dashboard\Services;

use App\Models\KabataanRegistration;
use App\Modules\KabataanMonitoring\Support\KkProfilingDateRange;
use App\Modules\KabataanMonitoring\Support\KkProfilingReviewStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Municipality-wide KK Profiling demographics for the federation dashboard.
 * Counts only — no personal data is returned.
 */
class YouthDemographicsService
{
    /** @var list<string> */
    public const CIVIL_STATUS_LABELS = [
        'Single',
        'Married',
        'Widowed',
        'Divorced',
        'Separated',
        'Annulled',
        'Unknown',
        'Live-in',
    ];

    /** @var list<string> */
    public const AGE_GROUP_LABELS = [
        'Child Youth (15-17 yrs old)',
        'Core Youth (18-24 yrs old)',
        'Young Adult (25-30 yrs old)',
    ];

    /** @var list<string> */
    public const EDUCATION_LABELS = [
        'Elementary Level',
        'Elementary Grad',
        'High school level',
        'High school Grad',
        'Vocational Grad',
        'College Level',
        'College Grad',
        'Masters Level',
        'Masters Grad',
        'Doctorate Level',
        'Doctorate Graduate',
    ];

    /** @var list<string> */
    public const CLASSIFICATION_LABELS = [
        'In school Youth',
        'Out of School Youth',
        'Working Youth',
        'Youth w/ Specific Needs',
    ];

    /** @var list<string> */
    public const SPECIFIC_NEEDS_LABELS = [
        'Person w/ Disability',
        'Children In Conflict w/ Law',
        'Indigenous People',
    ];

    /** @var list<string> */
    public const YES_NO_LABELS = ['Yes', 'No'];

    /**
     * @return array{
     *     total: int,
     *     civil_status: list<array{label: string, count: int}>,
     *     age_groups: list<array{label: string, count: int}>,
     *     educational_background: list<array{label: string, count: int}>,
     *     youth_classification: list<array{label: string, count: int}>,
     *     specific_needs: list<array{label: string, count: int}>,
     *     sk_voter: list<array{label: string, count: int}>,
     *     national_voter: list<array{label: string, count: int}>,
     *     has_data: bool
     * }
     */
    public function aggregate(?int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $empty = $this->emptyPayload();

        if (
            ! Schema::hasTable('kabataan_registrations')
            || ! Schema::hasColumn('kabataan_registrations', 'evaluation_status')
        ) {
            return $empty;
        }

        $records = $this->loadRecords($tenantId, $dateFrom, $dateTo);
        if ($records->isEmpty()) {
            return $empty;
        }

        $civil = array_fill_keys(self::CIVIL_STATUS_LABELS, 0);
        $ages = array_fill_keys(self::AGE_GROUP_LABELS, 0);
        $education = array_fill_keys(self::EDUCATION_LABELS, 0);
        $classification = array_fill_keys(self::CLASSIFICATION_LABELS, 0);
        $specificNeeds = array_fill_keys(self::SPECIFIC_NEEDS_LABELS, 0);
        $skVoter = array_fill_keys(self::YES_NO_LABELS, 0);
        $nationalVoter = array_fill_keys(self::YES_NO_LABELS, 0);

        foreach ($records as $row) {
            $civil[$this->normalizeCivilStatus($row['civil_status'] ?? null)]++;

            $ageLabel = $this->resolveAgeGroup(
                $row['birthdate'] ?? null,
                $row['age'] ?? null,
                $row['youth_age_group'] ?? null
            );
            if ($ageLabel !== null) {
                $ages[$ageLabel]++;
            }

            $eduLabel = $this->normalizeEducation($row['education'] ?? null);
            if ($eduLabel !== null) {
                $education[$eduLabel]++;
            }

            $mainHits = [];
            $needHits = [];
            foreach ($this->classificationTokens($row['youth_classification'] ?? null) as $token) {
                $need = $this->normalizeSpecificNeed($token);
                if ($need !== null) {
                    $needHits[$need] = true;
                }
                $main = $this->normalizeClassification($token);
                if ($main !== null) {
                    $mainHits[$main] = true;
                }
            }
            foreach (array_keys($mainHits) as $main) {
                $classification[$main]++;
            }
            foreach (array_keys($needHits) as $need) {
                $specificNeeds[$need]++;
            }

            $sk = $this->normalizeYesNo($row['sk_voter'] ?? null);
            if ($sk !== null) {
                $skVoter[$sk]++;
            }

            $national = $this->normalizeYesNo($row['national_voter'] ?? null);
            if ($national !== null) {
                $nationalVoter[$national]++;
            }
        }

        $total = $records->count();

        return [
            'total' => $total,
            'civil_status' => $this->toLabelCounts($civil),
            'age_groups' => $this->toLabelCounts($ages),
            'educational_background' => $this->toLabelCounts($education),
            'youth_classification' => $this->toLabelCounts($classification),
            'specific_needs' => $this->toLabelCounts($specificNeeds),
            'sk_voter' => $this->toLabelCounts($skVoter),
            'national_voter' => $this->toLabelCounts($nationalVoter),
            'has_data' => $total > 0,
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     civil_status: list<array{label: string, count: int}>,
     *     age_groups: list<array{label: string, count: int}>,
     *     educational_background: list<array{label: string, count: int}>,
     *     youth_classification: list<array{label: string, count: int}>,
     *     specific_needs: list<array{label: string, count: int}>,
     *     sk_voter: list<array{label: string, count: int}>,
     *     national_voter: list<array{label: string, count: int}>,
     *     has_data: bool
     * }
     */
    public function emptyPayload(): array
    {
        return [
            'total' => 0,
            'civil_status' => $this->zeroCounts(self::CIVIL_STATUS_LABELS),
            'age_groups' => $this->zeroCounts(self::AGE_GROUP_LABELS),
            'educational_background' => $this->zeroCounts(self::EDUCATION_LABELS),
            'youth_classification' => $this->zeroCounts(self::CLASSIFICATION_LABELS),
            'specific_needs' => $this->zeroCounts(self::SPECIFIC_NEEDS_LABELS),
            'sk_voter' => $this->zeroCounts(self::YES_NO_LABELS),
            'national_voter' => $this->zeroCounts(self::YES_NO_LABELS),
            'has_data' => false,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadRecords(?int $tenantId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = KabataanRegistration::query()
            ->whereNull('deleted_at');

        KkProfilingReviewStatus::applyApprovedScope($query);

        if ($tenantId !== null && Schema::hasColumn('kabataan_registrations', 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        KkProfilingDateRange::apply($query, $dateFrom, $dateTo, 'approved');

        $registrations = $query->get(['id', 'form_data']);
        if ($registrations->isEmpty()) {
            return collect();
        }

        $surveyById = $this->surveyRowsByRegistrationId($registrations->pluck('id'));

        return $registrations->map(function (KabataanRegistration $registration) use ($surveyById) {
            $id = (int) $registration->id;
            $formData = is_array($registration->form_data) ? $registration->form_data : [];
            $survey = $surveyById->get($id);

            $civil = $this->firstNonEmpty(
                $survey['civil_status'] ?? null,
                $this->formScalar($formData, ['civil_status', 'civilStatus'])
            );

            $birthdate = $this->firstNonEmpty(
                $survey['birthdate'] ?? null,
                $this->formScalar($formData, ['birthday', 'birthdate', 'date_of_birth'])
            );

            $age = $survey['age'] ?? null;
            if (! is_numeric($age)) {
                $formAge = $this->formScalar($formData, ['age']);
                $age = is_numeric($formAge) ? (int) $formAge : null;
            } else {
                $age = (int) $age;
            }

            $education = $this->firstNonEmpty(
                $survey['educational_background'] ?? null,
                $this->formScalar($formData, ['education', 'educational_background', 'educationalBackground'])
            );

            $classification = $survey['youth_classification'] ?? null;
            if ($classification === null || $classification === '') {
                $classification = $formData['youth_classification']
                    ?? $formData['youthClassification']
                    ?? null;
            }

            $skVoter = $survey !== null
                ? $this->boolToYesNo($survey['registered_sk_voter'] ?? null)
                : $this->formScalar($formData, ['sk_voter', 'registered_sk_voter', 'registeredSKVoter']);

            $nationalVoter = $survey !== null
                ? $this->boolToYesNo($survey['registered_national_voter'] ?? null)
                : $this->formScalar($formData, ['national_voter', 'registered_national_voter', 'registeredNationalVoter']);

            return [
                'civil_status' => $civil,
                'birthdate' => $birthdate,
                'age' => $age,
                'youth_age_group' => $this->firstNonEmpty(
                    $survey['youth_age_group'] ?? null,
                    $this->formScalar($formData, ['youth_age_group', 'youthAgeGroup'])
                ),
                'education' => $education,
                'youth_classification' => $classification,
                'sk_voter' => $skVoter,
                'national_voter' => $nationalVoter,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, mixed>  $registrationIds
     * @return Collection<int, array<string, mixed>>
     */
    private function surveyRowsByRegistrationId(Collection $registrationIds): Collection
    {
        if (! Schema::hasTable('kk_survey_responses')) {
            return collect();
        }

        $ids = $registrationIds
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        $columns = array_values(array_filter([
            'kabataan_registration_id',
            Schema::hasColumn('kk_survey_responses', 'civil_status') ? 'civil_status' : null,
            Schema::hasColumn('kk_survey_responses', 'birthdate') ? 'birthdate' : null,
            Schema::hasColumn('kk_survey_responses', 'age') ? 'age' : null,
            Schema::hasColumn('kk_survey_responses', 'youth_age_group') ? 'youth_age_group' : null,
            Schema::hasColumn('kk_survey_responses', 'educational_background') ? 'educational_background' : null,
            Schema::hasColumn('kk_survey_responses', 'youth_classification') ? 'youth_classification' : null,
            Schema::hasColumn('kk_survey_responses', 'registered_sk_voter') ? 'registered_sk_voter' : null,
            Schema::hasColumn('kk_survey_responses', 'registered_national_voter') ? 'registered_national_voter' : null,
        ]));

        return DB::table('kk_survey_responses')
            ->whereIn('kabataan_registration_id', $ids)
            ->get($columns)
            ->mapWithKeys(function (object $row): array {
                $data = (array) $row;
                $id = (int) ($data['kabataan_registration_id'] ?? 0);

                return $id > 0 ? [$id => $data] : [];
            });
    }

    private function applyApprovedDateRange($query, ?string $dateFrom, ?string $dateTo): void
    {
        $from = $this->parseDate($dateFrom);
        $to = $this->parseDate($dateTo);
        if ($from === null || $to === null) {
            return;
        }

        $from = $from->startOfDay();
        $to = $to->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $query->where(function ($builder) use ($from, $to): void {
            $builder->where(function ($inner) use ($from, $to): void {
                $inner->whereNotNull('reviewed_at')
                    ->where('reviewed_at', '>=', $from)
                    ->where('reviewed_at', '<=', $to);
            })->orWhere(function ($inner) use ($from, $to): void {
                $inner->whereNull('reviewed_at')
                    ->whereNotNull('submitted_at')
                    ->where('submitted_at', '>=', $from)
                    ->where('submitted_at', '<=', $to);
            });
        });
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeCivilStatus(mixed $raw): string
    {
        $value = $this->normalizeKey($raw);
        if ($value === '') {
            return 'Unknown';
        }

        $map = [
            'single' => 'Single',
            'married' => 'Married',
            'widowed' => 'Widowed',
            'divorced' => 'Divorced',
            'separated' => 'Separated',
            'annulled' => 'Annulled',
            'unknown' => 'Unknown',
            'live-in' => 'Live-in',
            'live in' => 'Live-in',
            'livein' => 'Live-in',
        ];

        return $map[$value] ?? 'Unknown';
    }

    private function resolveAgeGroup(mixed $birthdate, mixed $age, mixed $storedGroup): ?string
    {
        $computedAge = null;
        $birthday = trim((string) ($birthdate ?? ''));
        if ($birthday !== '') {
            try {
                $computedAge = Carbon::parse($birthday)->age;
            } catch (\Throwable) {
                $computedAge = null;
            }
        }

        if ($computedAge === null && is_numeric($age)) {
            $computedAge = (int) $age;
        }

        if ($computedAge !== null) {
            if ($computedAge >= 15 && $computedAge <= 17) {
                return 'Child Youth (15-17 yrs old)';
            }
            if ($computedAge >= 18 && $computedAge <= 24) {
                return 'Core Youth (18-24 yrs old)';
            }
            if ($computedAge >= 25 && $computedAge <= 30) {
                return 'Young Adult (25-30 yrs old)';
            }

            return null;
        }

        $value = $this->normalizeKey($storedGroup);
        if ($value === '') {
            return null;
        }
        if (str_contains($value, 'child')) {
            return 'Child Youth (15-17 yrs old)';
        }
        if (str_contains($value, 'core')) {
            return 'Core Youth (18-24 yrs old)';
        }
        if (str_contains($value, 'young adult')) {
            return 'Young Adult (25-30 yrs old)';
        }

        return null;
    }

    private function normalizeEducation(mixed $raw): ?string
    {
        $value = $this->normalizeKey($raw);
        if ($value === '') {
            return null;
        }

        $map = [
            'elementary level' => 'Elementary Level',
            'elementary grad' => 'Elementary Grad',
            'elementary graduate' => 'Elementary Grad',
            'high school level' => 'High school level',
            'highschool level' => 'High school level',
            'high school grad' => 'High school Grad',
            'high school graduate' => 'High school Grad',
            'highschool grad' => 'High school Grad',
            'vocational grad' => 'Vocational Grad',
            'vocational graduate' => 'Vocational Grad',
            'college level' => 'College Level',
            'college grad' => 'College Grad',
            'college graduate' => 'College Grad',
            'masters level' => 'Masters Level',
            "master's level" => 'Masters Level',
            'masters grad' => 'Masters Grad',
            'masters graduate' => 'Masters Grad',
            "master's grad" => 'Masters Grad',
            "master's graduate" => 'Masters Grad',
            'doctorate level' => 'Doctorate Level',
            'doctorate grad' => 'Doctorate Graduate',
            'doctorate graduate' => 'Doctorate Graduate',
        ];

        return $map[$value] ?? null;
    }

    private function normalizeClassification(mixed $raw): ?string
    {
        $value = $this->normalizeKey($raw);
        if ($value === '') {
            return null;
        }

        if (
            str_contains($value, 'person w')
            || str_contains($value, 'disability')
            || str_contains($value, 'pwd')
            || str_contains($value, 'conflict')
            || str_contains($value, 'cicl')
            || str_contains($value, 'indigenous')
            || $value === 'ip'
            || str_contains($value, 'specific need')
        ) {
            return 'Youth w/ Specific Needs';
        }

        if (str_contains($value, 'in school') || str_contains($value, 'inschool')) {
            return 'In school Youth';
        }
        if (str_contains($value, 'out of school') || str_contains($value, 'out-of-school')) {
            return 'Out of School Youth';
        }
        if (str_contains($value, 'working')) {
            return 'Working Youth';
        }

        return null;
    }

    private function normalizeSpecificNeed(mixed $raw): ?string
    {
        $value = $this->normalizeKey($raw);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'disability') || $value === 'pwd' || str_contains($value, 'person w')) {
            return 'Person w/ Disability';
        }
        if (str_contains($value, 'conflict') || $value === 'cicl') {
            return 'Children In Conflict w/ Law';
        }
        if (str_contains($value, 'indigenous') || $value === 'ip') {
            return 'Indigenous People';
        }

        return null;
    }

    private function normalizeYesNo(mixed $raw): ?string
    {
        if (is_bool($raw)) {
            return $raw ? 'Yes' : 'No';
        }

        $value = $this->normalizeKey($raw);
        if ($value === '') {
            return null;
        }

        if (in_array($value, ['yes', 'true', '1', 'y', 't'], true)) {
            return 'Yes';
        }
        if (in_array($value, ['no', 'false', '0', 'n', 'f'], true)) {
            return 'No';
        }

        return null;
    }

    private function boolToYesNo(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeYesNo($value);
    }

    /**
     * @return list<string>
     */
    private function classificationTokens(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw) && (str_starts_with(trim($raw), '[') || str_starts_with(trim($raw), '{'))) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (is_array($raw)) {
            $tokens = [];
            foreach ($raw as $item) {
                if (is_array($item)) {
                    continue;
                }
                $trimmed = trim((string) $item);
                if ($trimmed !== '') {
                    $tokens[] = $trimmed;
                }
            }

            return $tokens;
        }

        $string = trim((string) $raw);
        if ($string === '') {
            return [];
        }

        if (str_contains($string, ',') || str_contains($string, '|') || str_contains($string, ';')) {
            return array_values(array_filter(array_map(
                'trim',
                preg_split('/[,|;]+/', $string) ?: []
            )));
        }

        return [$string];
    }

    /**
     * @param  array<string, mixed>  $formData
     * @param  list<string>  $keys
     */
    private function formScalar(array $formData, array $keys): string
    {
        foreach ($keys as $key) {
            foreach ($formData as $field => $value) {
                if (strcasecmp((string) $field, $key) !== 0) {
                    continue;
                }

                if (is_array($value)) {
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            continue;
                        }
                        $trimmed = trim((string) $item);
                        if ($trimmed !== '') {
                            return $trimmed;
                        }
                    }

                    continue;
                }

                $trimmed = trim((string) $value);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return '';
    }

    private function firstNonEmpty(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || is_bool($candidate)) {
                continue;
            }
            $trimmed = trim((string) $candidate);
            if ($trimmed !== '' && $trimmed !== '—') {
                return $trimmed;
            }
        }

        return null;
    }

    private function normalizeKey(mixed $raw): string
    {
        if (is_array($raw)) {
            $raw = $raw[0] ?? '';
        }

        $value = mb_strtolower(trim((string) $raw), 'UTF-8');

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    /**
     * @param  list<string>  $labels
     * @return list<array{label: string, count: int}>
     */
    private function zeroCounts(array $labels): array
    {
        return array_map(
            fn (string $label) => ['label' => $label, 'count' => 0],
            $labels
        );
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{label: string, count: int}>
     */
    private function toLabelCounts(array $counts): array
    {
        $items = [];
        foreach ($counts as $label => $count) {
            $items[] = ['label' => $label, 'count' => (int) $count];
        }

        return $items;
    }
}
