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
    private const CACHE_TTL_SECONDS = 300;

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
        $year = (int) $request->input('year', now()->year);
        $month = max(1, min(12, (int) $request->input('month', now()->month)));

        $cacheKey = sprintf(
            'skfed:dash:kk-chart:%d:%s:%s:%d:%d',
            $tenantId,
            $barangayId === '' ? 'all' : $barangayId,
            $period,
            $year,
            $month
        );

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenantId, $barangayId, $period, $year, $month): array {
            $barangayIds = Barangay::query()
                ->where('tenant_id', $tenantId)
                ->pluck('id');

            if ($barangayId !== 'all' && $barangayId !== '' && $barangayId !== null) {
                $barangayIds = $barangayIds->filter(fn (int $id) => $id === (int) $barangayId)->values();
            }

            if ($barangayIds->isEmpty()) {
                return $this->emptyPayload($period);
            }

            if ($period === 'weekly') {
                return $this->weeklyPayload($tenantId, $barangayIds->all(), $year, $month);
            }

            return $this->monthlyPayload($tenantId, $barangayIds->all(), $year);
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
        $yearInput = $request->input('year', 'all');
        $yearKey = ($yearInput === 'all' || $yearInput === '' || $yearInput === null) ? 'all' : (string) (int) $yearInput;

        $cacheKey = sprintf(
            'skfed:dash:purok-chart:%d:%s:%s:%s',
            $tenantId,
            $barangayIdInput === '' ? 'all' : $barangayIdInput,
            $purokFilter === '' ? 'all' : $purokFilter,
            $yearKey
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
        $yearInput = $request->input('year', 'all');
        $year = ($yearInput === 'all' || $yearInput === '' || $yearInput === null) ? null : (int) $yearInput;

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

        if ($year !== null) {
            $query->where(function ($yearQuery) use ($year): void {
                $yearQuery->whereYear('reviewed_at', $year)
                    ->orWhere(function ($createdQuery) use ($year): void {
                        $createdQuery->whereNull('reviewed_at')->whereYear('created_at', $year);
                    });
            });
        }

        $records = $query->get(['id', 'barangay_id', 'form_data']);

        if ($barangayId === null) {
            $countsByBarangay = array_fill_keys($catalogNames, 0);
            $nameById = $barangays->pluck('name', 'id');

            foreach ($records as $record) {
                $name = $nameById[$record->barangay_id] ?? null;
                if ($name === null || ! array_key_exists($name, $countsByBarangay)) {
                    continue;
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
    private function monthlyPayload(int $tenantId, array $barangayIds, int $year): array
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return [
            'period' => 'monthly',
            'year' => $year,
            'labels' => $labels,
            'approved' => $this->countMonthly($tenantId, $barangayIds, $year, 'approved'),
            'pending' => $this->countMonthly($tenantId, $barangayIds, $year, 'pending'),
            'rejected' => $this->countMonthly($tenantId, $barangayIds, $year, 'rejected'),
        ];
    }

    /**
     * @param  list<int>  $barangayIds
     * @return array<string, mixed>
     */
    private function weeklyPayload(int $tenantId, array $barangayIds, int $year, int $month): array
    {
        $weeks = $this->buildWeeksForMonth($year, $month);
        $labels = array_column($weeks, 'label');
        $approved = [];
        $pending = [];
        $rejected = [];

        foreach ($weeks as $week) {
            $approved[] = $this->countWeekly($tenantId, $barangayIds, $week['start'], $week['end'], 'approved');
            $pending[] = $this->countWeekly($tenantId, $barangayIds, $week['start'], $week['end'], 'pending');
            $rejected[] = $this->countWeekly($tenantId, $barangayIds, $week['start'], $week['end'], 'rejected');
        }

        return [
            'period' => 'weekly',
            'year' => $year,
            'month' => $month,
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
    private function countMonthly(int $tenantId, array $barangayIds, int $year, string $category): array
    {
        $counts = array_fill(0, 12, 0);
        $dateExpression = $this->dateExpression($category);

        $rows = $this->baseQuery($tenantId, $barangayIds, $category)
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
     * @return array<string, mixed>
     */
    private function emptyPayload(string $period): array
    {
        return [
            'period' => $period,
            'year' => now()->year,
            'labels' => $period === 'weekly' ? [] : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'approved' => [],
            'pending' => [],
            'rejected' => [],
        ];
    }
}
