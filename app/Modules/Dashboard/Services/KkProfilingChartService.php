<?php

namespace App\Modules\Dashboard\Services;

use App\Models\KabataanRegistration;
use App\Modules\Accounts\Models\Barangay;
use App\Modules\Shared\Models\User;
use App\Support\OpaqueUrlId;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KkProfilingChartService
{
    private const CACHE_TTL_SECONDS = 60;

    private const KK_PROFILING_CACHE_TTL_SECONDS = 5;

    /**
     * @return array<string, mixed>
     */
    public function getChartData(User $user, Request $request): array
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return $this->emptyPayload();
        }

        $tenantId = (int) $user->tenant_id;
        $barangayToken = (string) $request->input('barangay_id', 'all');
        $barangayId = $barangayToken === 'all' || $barangayToken === ''
            ? null
            : OpaqueUrlId::decode($barangayToken);
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $cacheKey = sprintf(
            'skfed:dash:kk-chart:v7:%d:%s:%s:%s',
            $tenantId,
            $barangayId ?? 'all',
            $dateFrom->toDateString(),
            $dateTo->toDateString()
        );

        return Cache::store('file')->remember($cacheKey, self::KK_PROFILING_CACHE_TTL_SECONDS, function () use ($tenantId, $barangayId, $dateFrom, $dateTo): array {
            $barangayIds = Barangay::query()
                ->where('tenant_id', $tenantId)
                ->pluck('id');

            if ($barangayId !== null) {
                $barangayIds = $barangayIds->filter(fn ($id) => (int) $id === $barangayId)->values();
            }

            if ($barangayIds->isEmpty()) {
                return $this->emptyPayload($dateFrom, $dateTo);
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
        $barangayId = $barangayIdInput === 'all' || $barangayIdInput === ''
            ? null
            : OpaqueUrlId::decode($barangayIdInput);
        $purokFilter = trim((string) $request->input('purok', 'all'));
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);

        $cacheKey = sprintf(
            'skfed:dash:purok-chart:v4:%d:%s:%s:%s:%s',
            $tenantId,
            $barangayId ?? 'all',
            $purokFilter === '' ? 'all' : $purokFilter,
            $dateFrom->toDateString(),
            $dateTo->toDateString()
        );

        return Cache::store('file')->remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($user, $request): array {
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
            ? OpaqueUrlId::decode((string) $barangayIdInput)
            : null;

        if ($barangayId !== null) {
            abort_unless(
                Barangay::query()->where('tenant_id', $tenantId)->whereKey($barangayId)->exists(),
                404
            );
        }

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
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'labels' => array_column($buckets, 'label'),
            'approved' => $this->countByMonthBuckets($tenantId, $barangayIds, 'approved', $dateFrom, $dateTo, $buckets),
            'pending' => $this->countByMonthBuckets($tenantId, $barangayIds, 'pending', $dateFrom, $dateTo, $buckets),
            'rejected' => $this->countByMonthBuckets($tenantId, $barangayIds, 'rejected', $dateFrom, $dateTo, $buckets),
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function monthBuckets(Carbon $dateFrom, Carbon $dateTo): array
    {
        $buckets = [];
        $multipleYears = $dateFrom->year !== $dateTo->year;
        $cursor = $dateFrom->copy()->startOfYear();
        $lastMonth = $dateTo->copy()->endOfYear()->startOfMonth();

        while ($cursor->lte($lastMonth)) {
            $buckets[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format($multipleYears ? 'M Y' : 'F'),
            ];

            $cursor->addMonth();
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
    private function emptyPayload(?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $from = $dateFrom ?? now()->copy()->startOfYear();
        $to = $dateTo ?? now()->copy()->startOfDay();

        $buckets = $this->monthBuckets($from, $to);
        $zeros = array_fill(0, count($buckets), 0);

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'labels' => array_column($buckets, 'label'),
            'approved' => $zeros,
            'pending' => $zeros,
            'rejected' => $zeros,
        ];
    }
}
