<?php

namespace App\Modules\Dashboard\Services;

use App\Models\KabataanRegistration;
use App\Modules\Accounts\Models\Barangay;
use App\Modules\BarangayMonitoring\Services\BarangayMonitoringService;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        $year = (int) $dateFrom->year;
        $month = max(1, min(12, (int) $request->input('month', $dateFrom->month)));

        $cacheKey = sprintf(
            'skfed:dash:kk-chart:%d:%s:%s:%s:%s:%d',
            $tenantId,
            $barangayId === '' ? 'all' : $barangayId,
            $period,
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
            $month
        );

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenantId, $barangayId, $period, $year, $month, $dateFrom, $dateTo): array {
            $barangayIds = Barangay::query()
                ->where('tenant_id', $tenantId)
                ->pluck('id');

            if ($barangayId !== 'all' && $barangayId !== '' && $barangayId !== null) {
                $barangayIds = $barangayIds->filter(fn (int $id) => $id === (int) $barangayId)->values();
            }

            if ($barangayIds->isEmpty()) {
                return $this->emptyPayload($period, $dateFrom, $dateTo);
            }

            if ($period === 'weekly') {
                return $this->weeklyPayload($tenantId, $barangayIds->all(), $year, $month, $dateFrom, $dateTo);
            }

            return $this->monthlyPayload($tenantId, $barangayIds->all(), $year, $dateFrom, $dateTo);
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
            'skfed:dash:purok-chart:v2:%d:%s:%s:%s:%s',
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
        $catalogNames = array_values(app(BarangayMonitoringService::class)->slugToNameMap());
        $empty = [
            'labels' => $catalogNames,
            'counts' => array_fill(0, count($catalogNames), 0),
            'zones' => [],
        ];

        $tenantId = (int) $user->tenant_id;
        $barangayIdInput = $request->input('barangay_id', 'all');
        $purokFilter = trim((string) $request->input('purok', 'all'));
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $barangays = Barangay::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $barangayId = ($barangayIdInput !== 'all' && $barangayIdInput !== '' && $barangayIdInput !== null)
            ? (int) $barangayIdInput
            : null;

        $zones = $barangayId ? $this->zonesForBarangay($barangayId) : [];

        if (! Schema::hasTable('kabataan_registrations')) {
            return $barangayId ? ['labels' => $zones, 'counts' => array_fill(0, count($zones), 0), 'zones' => $zones] : $empty;
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
            // Show every barangay in the tenant (including zero-count) so mobile/desktop charts stay complete.
            $countsByBarangay = [];
            foreach ($barangays as $barangay) {
                $name = trim((string) $barangay->name);
                if ($name === '') {
                    continue;
                }
                $countsByBarangay[$name] = 0;
            }
            if ($countsByBarangay === []) {
                $countsByBarangay = array_fill_keys($catalogNames, 0);
            }

            $nameById = $barangays->pluck('name', 'id');

            foreach ($records as $record) {
                $name = $nameById[$record->barangay_id] ?? null;
                if ($name === null) {
                    continue;
                }
                $name = trim((string) $name);
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
    private function monthlyPayload(int $tenantId, array $barangayIds, int $year, Carbon $dateFrom, Carbon $dateTo): array
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return [
            'period' => 'monthly',
            'year' => $year,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'labels' => $labels,
            'approved' => $this->countMonthly($tenantId, $barangayIds, $year, 'approved', $dateFrom, $dateTo),
            'pending' => $this->countMonthly($tenantId, $barangayIds, $year, 'pending', $dateFrom, $dateTo),
            'rejected' => $this->countMonthly($tenantId, $barangayIds, $year, 'rejected', $dateFrom, $dateTo),
        ];
    }

    /**
     * @param  list<int>  $barangayIds
     * @return array<string, mixed>
     */
    private function weeklyPayload(int $tenantId, array $barangayIds, int $year, int $month, Carbon $dateFrom, Carbon $dateTo): array
    {
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
     * @param  list<int>  $barangayIds
     * @return list<int>
     */
    private function countMonthly(int $tenantId, array $barangayIds, int $year, string $category, Carbon $dateFrom, Carbon $dateTo): array
    {
        $counts = array_fill(0, 12, 0);
        $dateExpression = $this->dateExpression($category);

        $rows = $this->baseQuery($tenantId, $barangayIds, $category)
            ->whereRaw("{$dateExpression}::date BETWEEN ? AND ?", [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereRaw("EXTRACT(YEAR FROM {$dateExpression}) = ?", [$year])
            ->selectRaw("EXTRACT(MONTH FROM {$dateExpression})::int AS bucket, COUNT(*) AS total")
            ->groupBy('bucket')
            ->get();

        foreach ($rows as $row) {
            $index = ((int) $row->bucket) - 1;
            if ($index >= 0 && $index < 12) {
                $counts[$index] = (int) $row->total;
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

        return (int) $this->baseQuery($tenantId, $barangayIds, $category)
            ->whereRaw("{$dateExpression}::date BETWEEN ? AND ?", [$start, $end])
            ->count();
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

        return match ($category) {
            'approved' => $query->whereIn('evaluation_status', ['active', 'Auto Approved']),
            'pending' => $query
                ->whereIn('evaluation_status', ['Not Profiled', 'Wrong Credentials'])
                ->where('status', '!=', 'rejected'),
            'rejected' => $query->where('status', 'rejected'),
            default => $query,
        };
    }

    private function dateExpression(string $category): string
    {
        return match ($category) {
            'pending' => 'COALESCE(submitted_at, created_at)',
            default => 'COALESCE(reviewed_at, submitted_at, created_at)',
        };
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
        $from = $dateFrom?->toDateString() ?? now()->startOfYear()->toDateString();
        $to = $dateTo?->toDateString() ?? now()->toDateString();

        return [
            'period' => $period,
            'year' => $dateFrom?->year ?? now()->year,
            'date_from' => $from,
            'date_to' => $to,
            'labels' => $period === 'weekly' ? [] : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'approved' => [],
            'pending' => [],
            'rejected' => [],
        ];
    }
}
