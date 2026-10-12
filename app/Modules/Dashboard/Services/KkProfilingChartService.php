<?php

namespace App\Modules\Dashboard\Services;

use App\Models\KabataanRegistration;
use App\Modules\Accounts\Models\Barangay;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KkProfilingChartService
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * @return array<string, mixed>
     */
    public function getChartData(User $user, Request $request): array
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return $this->emptyPayload('monthly');
        }

        $tenantId = (int) $user->tenant_id;
        $barangayId = (string) $request->input('barangay_id', 'all');
        $period = $request->input('period', 'monthly') === 'weekly' ? 'weekly' : 'monthly';
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $month = max(1, min(12, (int) $request->input('month', $dateTo->month)));

        $cacheKey = sprintf(
            'skfed:dash:kk-chart:v5:%d:%s:%s:%s:%s:%d',
            $tenantId,
            $barangayId === '' ? 'all' : $barangayId,
            $period,
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
            $month
        );

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenantId, $barangayId, $period, $month, $dateFrom, $dateTo): array {
            $barangayIds = Barangay::query()
                ->where('tenant_id', $tenantId)
                ->pluck('id');

            if ($barangayId !== 'all' && $barangayId !== '' && $barangayId !== null) {
                $barangayIds = $barangayIds->filter(fn ($id) => (int) $id === (int) $barangayId)->values();
            }

            if ($barangayIds->isEmpty()) {
                return $this->emptyPayload($period, $dateFrom, $dateTo);
            }

            if ($period === 'weekly') {
                $weekMonth = $this->resolveWeekMonth($dateFrom, $dateTo, $month);

                return $this->weeklyPayload($tenantId, $barangayIds->all(), $weekMonth, $dateFrom, $dateTo);
            }

            return $this->monthlyPayload($tenantId, $barangayIds->all(), $dateFrom, $dateTo);
        });
    }

    /**
     * @return array{labels: list<string>, counts: list<int>, zones: list<string>}
     */
    public function getPurokChartData(User $user, Request $request): array
    {
        $tenantId = (int) $user->tenant_id;
        $barangayIdInput = (string) $request->input('barangay_id', 'all');
        $purokFilter = trim((string) $request->input('purok', 'all'));
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $cacheKey = sprintf(
            'skfed:dash:purok-chart:v4:%d:%s:%s:%s:%s',
            $tenantId,
            $barangayIdInput === '' ? 'all' : $barangayIdInput,
            $purokFilter === '' ? 'all' : $purokFilter,
            $dateFrom->toDateString(),
            $dateTo->toDateString()
        );

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($user, $request): array {
            return $this->buildPurokChartData($user, $request);
        });
    }

    /**
     * @return array{labels: list<string>, counts: list<int>, zones: list<string>}
     */
    private function buildPurokChartData(User $user, Request $request): array
    {
        $tenantId = (int) $user->tenant_id;
        $barangayIdInput = $request->input('barangay_id', 'all');
        $purokFilter = trim((string) $request->input('purok', 'all'));
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $barangayId = ($barangayIdInput !== 'all' && $barangayIdInput !== '' && $barangayIdInput !== null)
            ? (int) $barangayIdInput
            : null;

        $zones = $barangayId ? $this->zonesForBarangay($barangayId) : [];

        if (! Schema::hasTable('kabataan_registrations')) {
            if ($barangayId) {
                return ['labels' => $zones, 'counts' => array_fill(0, count($zones), 0), 'zones' => $zones];
            }

            return $this->buildAllBarangaysByBarangayPayload($tenantId, collect());
        }

        $query = KabataanRegistration::query()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'email_verified', 'password_set']);

        if (Schema::hasColumn('kabataan_registrations', 'evaluation_status')) {
            $query->where(function ($evaluationQuery): void {
                $evaluationQuery->whereNull('evaluation_status')
                    ->orWhereIn('evaluation_status', ['active', 'Auto Approved', 'ID Verified']);
            });
        }

        if ($barangayId !== null) {
            $query->where('barangay_id', $barangayId);
        }

        $query->where(function ($dateQuery) use ($dateFrom, $dateTo): void {
            $dateQuery->where(function ($reviewedQuery) use ($dateFrom, $dateTo): void {
                $reviewedQuery->whereNotNull('reviewed_at')
                    ->whereDate('reviewed_at', '>=', $dateFrom->toDateString())
                    ->whereDate('reviewed_at', '<=', $dateTo->toDateString());
            })->orWhere(function ($createdQuery) use ($dateFrom, $dateTo): void {
                $createdQuery->whereNull('reviewed_at')
                    ->whereDate('created_at', '>=', $dateFrom->toDateString())
                    ->whereDate('created_at', '<=', $dateTo->toDateString());
            });
        });

        $records = $query->get(['id', 'barangay_id', 'form_data']);

        if ($barangayId === null) {
            // All Barangays: one bar per barangay name (not sitio/purok).
            return $this->buildAllBarangaysByBarangayPayload($tenantId, $records);
        }

        $counts = [];
        foreach ($zones as $zoneName) {
            $counts[$zoneName] = 0;
        }

        foreach ($records as $record) {
            $formData = is_array($record->form_data) ? $record->form_data : [];
            $purok = $this->formValue($formData, 'purok_zone');
            if ($purok === '—' || $purok === '') {
                continue;
            }
            if (! array_key_exists($purok, $counts)) {
                $counts[$purok] = 0;
            }
            $counts[$purok]++;
        }

        if ($purokFilter !== '' && $purokFilter !== 'all') {
            $counts = [$purokFilter => $counts[$purokFilter] ?? 0];
        }

        return [
            'labels' => array_keys($counts),
            'counts' => array_values($counts),
            'zones' => $zones,
        ];
    }

    /**
     * All Barangays view: youth counts labeled by barangay name.
     *
     * @param  Collection<int, KabataanRegistration>|\Illuminate\Database\Eloquent\Collection<int, KabataanRegistration>  $records
     * @return array{labels: list<string>, counts: list<int>, zones: list<string>}
     */
    private function buildAllBarangaysByBarangayPayload(int $tenantId, $records): array
    {
        $barangays = Barangay::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $countsByBarangay = [];
        foreach ($barangays as $barangay) {
            $name = trim((string) $barangay->name);
            if ($name === '') {
                continue;
            }
            $countsByBarangay[$name] = 0;
        }

        if ($countsByBarangay === []) {
            return [
                'labels' => [],
                'counts' => [],
                'zones' => [],
            ];
        }

        $nameById = $barangays->pluck('name', 'id');

        foreach ($records as $record) {
            $rawName = $nameById[$record->barangay_id] ?? null;
            if ($rawName === null) {
                continue;
            }
            $name = trim((string) $rawName);
            if ($name === '') {
                continue;
            }
            if (! array_key_exists($name, $countsByBarangay)) {
                $countsByBarangay[$name] = 0;
            }
            $countsByBarangay[$name]++;
        }

        return [
            'labels' => array_keys($countsByBarangay),
            'counts' => array_values($countsByBarangay),
            'zones' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private function zonesForBarangay(int $barangayId): array
    {
        if (! Schema::hasTable('barangay_zones')) {
            return [];
        }

        return DB::table('barangay_zones')
            ->where('barangay_id', $barangayId)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => (string) $name)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function formValue(array $formData, string $key): string
    {
        $value = $formData[$key] ?? $formData['purokZone'] ?? '—';

        if (is_array($value)) {
            $value = $value[0] ?? '—';
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : '—';
    }

    /**
     * @param  list<int>  $barangayIds
     * @return array<string, mixed>
     */
    private function monthlyPayload(int $tenantId, array $barangayIds, Carbon $dateFrom, Carbon $dateTo): array
    {
        $buckets = $this->monthBuckets($dateFrom, $dateTo);

        return [
            'period' => 'monthly',
            'year' => (int) $dateTo->year,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'labels' => array_column($buckets, 'label'),
            'approved' => $this->countByMonthBuckets($tenantId, $barangayIds, 'approved', $dateFrom, $dateTo, $buckets),
            'pending' => $this->countByMonthBuckets($tenantId, $barangayIds, 'pending', $dateFrom, $dateTo, $buckets),
            'rejected' => $this->countByMonthBuckets($tenantId, $barangayIds, 'rejected', $dateFrom, $dateTo, $buckets),
        ];
    }

    /**
     * @param  list<int>  $barangayIds
     * @return array<string, mixed>
     */
    private function weeklyPayload(int $tenantId, array $barangayIds, Carbon $monthStart, Carbon $dateFrom, Carbon $dateTo): array
    {
        $year = (int) $monthStart->year;
        $month = (int) $monthStart->month;
        $weeks = $this->buildWeeksForMonth($year, $month);
        $labels = array_column($weeks, 'label');
        $approved = [];
        $pending = [];
        $rejected = [];

        foreach ($weeks as $week) {
            $weekStart = Carbon::parse($week['start'])->startOfDay();
            $weekEnd = Carbon::parse($week['end'])->endOfDay();
            $clipStart = $weekStart->lt($dateFrom) ? $dateFrom->copy()->startOfDay() : $weekStart;
            $clipEnd = $weekEnd->gt($dateTo) ? $dateTo->copy()->endOfDay() : $weekEnd;

            if ($clipStart->gt($clipEnd)) {
                $approved[] = 0;
                $pending[] = 0;
                $rejected[] = 0;

                continue;
            }

            $approved[] = $this->countWeekly($tenantId, $barangayIds, $clipStart->toDateString(), $clipEnd->toDateString(), 'approved');
            $pending[] = $this->countWeekly($tenantId, $barangayIds, $clipStart->toDateString(), $clipEnd->toDateString(), 'pending');
            $rejected[] = $this->countWeekly($tenantId, $barangayIds, $clipStart->toDateString(), $clipEnd->toDateString(), 'rejected');
        }

        return [
            'period' => 'weekly',
            'year' => $year,
            'month' => $month,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'labels' => $labels,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
        ];
    }

    /**
     * @return list<array{week: int, label: string, start: string, end: string}>
     */
    private function buildWeeksForMonth(int $year, int $month): array
    {
        $weeks = [];
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $cursor = $monthStart->copy()->startOfWeek(Carbon::MONDAY);

        while ($cursor->lte($monthEnd)) {
            $weekStart = $cursor->copy();
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);

            if ($weekEnd->lt($monthStart) || $weekStart->gt($monthEnd)) {
                $cursor->addWeek();

                continue;
            }

            $clipStart = $weekStart->lt($monthStart) ? $monthStart->copy() : $weekStart->copy();
            $clipEnd = $weekEnd->gt($monthEnd) ? $monthEnd->copy() : $weekEnd->copy();

            $weeks[] = [
                'week' => $weekStart->isoWeek(),
                'label' => sprintf(
                    'W%d (%s – %s)',
                    $weekStart->isoWeek(),
                    $clipStart->format('M j'),
                    $clipEnd->format('M j, Y')
                ),
                'start' => $clipStart->toDateString(),
                'end' => $clipEnd->toDateString(),
            ];

            $cursor->addWeek();
        }

        return $weeks;
    }

    /**
     * Prefer the selected calendar month nearest to the end of the dashboard range.
     */
    private function resolveWeekMonth(Carbon $dateFrom, Carbon $dateTo, int $month): Carbon
    {
        $month = max(1, min(12, $month));
        $candidate = Carbon::create($dateTo->year, $month, 1)->startOfMonth();

        while ($candidate->gt($dateTo) && $candidate->year > $dateFrom->year) {
            $candidate->subYear();
        }

        if ($candidate->copy()->endOfMonth()->lt($dateFrom)) {
            return $dateFrom->copy()->startOfMonth();
        }

        return $candidate;
    }

    /**
     * Always returns the 12 months January..December of the dashboard year.
     *
     * The selected range is clamped into the official term, which can start or end
     * mid-year. Bucketing on the clamped range alone would hide months the term does
     * not cover, so the axis is pinned to the full calendar year and months outside
     * the range are simply reported as zero by countByMonthBuckets().
     *
     * @return list<array{key: string, label: string}>
     */
    private function monthBuckets(Carbon $dateFrom, Carbon $dateTo): array
    {
        $year = (int) $dateTo->year;
        $buckets = [];

        for ($month = 1; $month <= 12; $month++) {
            $buckets[] = [
                'key' => sprintf('%04d-%02d', $year, $month),
                'label' => Carbon::create($year, $month, 1)->format('M'),
            ];
        }

        return $buckets;
    }

    /**
     * @param  list<int>  $barangayIds
     * @param  list<array{key: string, label: string}>  $buckets
     * @return list<int>
     */
    private function countByMonthBuckets(int $tenantId, array $barangayIds, string $category, Carbon $dateFrom, Carbon $dateTo, array $buckets): array
    {
        $counts = array_fill(0, count($buckets), 0);
        $indexByKey = [];
        foreach ($buckets as $index => $bucket) {
            $indexByKey[$bucket['key']] = $index;
        }

        $dateExpression = $this->dateExpression($category);
        $dateCast = $this->sqlDateCast($dateExpression);
        $yearMonth = $this->sqlYearMonth($dateExpression);
        $query = $this->baseQuery($tenantId, $barangayIds, $category);

        $rows = $query
            ->whereRaw("{$dateCast} BETWEEN ? AND ?", [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("{$yearMonth} AS bucket, COUNT(*) AS total")
            ->groupByRaw($yearMonth)
            ->get();

        foreach ($rows as $row) {
            $key = (string) $row->bucket;
            if (array_key_exists($key, $indexByKey)) {
                $counts[$indexByKey[$key]] = (int) $row->total;
            }
        }

        return $counts;
    }

    /**
     * @param  list<int>  $barangayIds
     */
    private function countWeekly(int $tenantId, array $barangayIds, string $start, string $end, string $category): int
    {
        $dateExpression = $this->dateExpression($category);
        $dateCast = $this->sqlDateCast($dateExpression);
        $query = $this->baseQuery($tenantId, $barangayIds, $category);

        return (int) $query
            ->whereRaw("{$dateCast} BETWEEN ? AND ?", [$start, $end])
            ->count();
    }

    private function sqlDateCast(string $expression): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "{$expression}::date",
            'sqlite' => "date({$expression})",
            default => "DATE({$expression})",
        };
    }

    private function sqlYearMonth(string $expression): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char({$expression}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$expression})",
            default => "DATE_FORMAT({$expression}, '%Y-%m')",
        };
    }

    /**
     * @param  list<int>  $barangayIds
     */
    private function baseQuery(int $tenantId, array $barangayIds, string $category)
    {
        $query = DB::table('kabataan_registrations')
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId)
            ->whereIn('barangay_id', $barangayIds);

        if (! Schema::hasColumn('kabataan_registrations', 'evaluation_status')) {
            return match ($category) {
                'rejected' => $query->where('status', 'rejected'),
                default => $query,
            };
        }

        return match ($category) {
            'approved' => $query
                ->where('status', '!=', 'rejected')
                ->whereIn('evaluation_status', ['active', 'Auto Approved', 'ID Verified']),
            'pending' => $query
                ->whereIn('evaluation_status', ['Not Profiled', 'Wrong Credentials'])
                ->where('status', '!=', 'rejected'),
            'rejected' => $query->where('status', 'rejected'),
            default => $query,
        };
    }

    private function dateExpression(string $category): string
    {
        $hasSubmitted = Schema::hasColumn('kabataan_registrations', 'submitted_at');
        $hasReviewed = Schema::hasColumn('kabataan_registrations', 'reviewed_at');

        if ($category === 'pending') {
            return $hasSubmitted ? 'COALESCE(submitted_at, created_at)' : 'created_at';
        }

        if ($hasReviewed && $hasSubmitted) {
            return 'COALESCE(reviewed_at, submitted_at, created_at)';
        }
        if ($hasSubmitted) {
            return 'COALESCE(submitted_at, created_at)';
        }
        if ($hasReviewed) {
            return 'COALESCE(reviewed_at, created_at)';
        }

        return 'created_at';
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $fallbackYear = (int) $request->input('year', now()->year);
        $rawFrom = trim((string) $request->input('date_from', ''));
        $rawTo = trim((string) $request->input('date_to', ''));

        try {
            $from = $rawFrom !== ''
                ? Carbon::parse($rawFrom)->startOfDay()
                : Carbon::create($fallbackYear, 1, 1)->startOfDay();
        } catch (\Throwable $e) {
            $from = Carbon::create($fallbackYear, 1, 1)->startOfDay();
        }

        try {
            $to = $rawTo !== ''
                ? Carbon::parse($rawTo)->startOfDay()
                : now()->startOfDay();
        } catch (\Throwable $e) {
            $to = now()->startOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(string $period, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $from = $dateFrom ?? now()->copy()->startOfYear();
        $to = $dateTo ?? now()->copy()->startOfDay();

        if ($period === 'weekly') {
            return [
                'period' => 'weekly',
                'year' => (int) $from->year,
                'month' => (int) $from->month,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'labels' => [],
                'approved' => [],
                'pending' => [],
                'rejected' => [],
            ];
        }

        $buckets = $this->monthBuckets($from, $to);
        $zeros = array_fill(0, count($buckets), 0);

        return [
            'period' => 'monthly',
            'year' => (int) $to->year,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'labels' => array_column($buckets, 'label'),
            'approved' => $zeros,
            'pending' => $zeros,
            'rejected' => $zeros,
        ];
    }
}
