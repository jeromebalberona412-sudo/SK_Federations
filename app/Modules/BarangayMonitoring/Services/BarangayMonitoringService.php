<?php

namespace App\Modules\BarangayMonitoring\Services;

use App\Models\Abyip;
use App\Models\ReportManagement;
use App\Modules\Profile\Models\Barangay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class BarangayMonitoringService
{
    /**
     * @return array<string, string>
     */
    public function slugToNameMap(): array
    {
        return [
            'alipit' => 'Alipit',
            'bagumbayan' => 'Bagumbayan',
            'bubukal' => 'Bubukal',
            'calios' => 'Calios',
            'duhat' => 'Duhat',
            'gatid' => 'Gatid',
            'jasaan' => 'Jasaan',
            'labuin' => 'Labuin',
            'malinao' => 'Malinao',
            'oogong' => 'Oogong',
            'pagsawitan' => 'Pagsawitan',
            'palasan' => 'Palasan',
            'patimbao' => 'Patimbao',
            'brgy-1-poblacion' => 'Poblacion I',
            'brgy-2-poblacion' => 'Poblacion II',
            'brgy-3-poblacion' => 'Poblacion III',
            'brgy-4-poblacion' => 'Poblacion IV',
            'brgy-5-poblacion' => 'Poblacion V',
            'san-jose' => 'San Jose',
            'san-juan' => 'San Juan',
            'san-pablo-norte' => 'San Pablo Norte',
            'san-pablo-sur' => 'San Pablo Sur',
            'santisima-cruz' => 'Santisima Cruz',
            'santo-angel-central' => 'Santo Angel Central',
            'santo-angel-norte' => 'Santo Angel Norte',
            'santo-angel-sur' => 'Santo Angel Sur',
        ];
    }

    public function nameFromSlug(string $slug): ?string
    {
        return $this->slugToNameMap()[$slug] ?? null;
    }

    public function resolveBarangayId(string $slug): ?int
    {
        $name = $this->nameFromSlug($slug);

        if ($name === null || ! Schema::hasTable('barangays')) {
            return null;
        }

        return Barangay::query()->where('name', $name)->value('id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAbyipReports(?int $barangayId): array
    {
        if ($barangayId === null || ! Schema::hasTable('abyip')) {
            return [];
        }

        return Abyip::query()
            ->documents()
            ->with(['creator:id,name', 'creator.officialProfile:id,user_id,position'])
            ->where('barangay_id', $barangayId)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', Abyip::STATUS_REJECTED);
            })
            ->orderByDesc('created_at')
            ->select($this->abyipDocumentListColumns())
            ->selectRaw("(CASE WHEN pdf_data IS NOT NULL AND pdf_data <> '' THEN 1 ELSE 0 END) as has_pdf_flag")
            ->get()
            ->map(fn (Abyip $document) => [
                'id' => $document->id,
                'name' => $document->document_title ?: 'ABYIP '.$document->fiscal_year,
                'title' => $document->document_title ?: 'ABYIP '.$document->fiscal_year,
                'fiscal_year' => $document->fiscal_year,
                'calendar_year' => $document->fiscal_year,
                'date_submitted' => $document->created_at?->toDateTimeString(),
                'submitted_by' => $document->creator?->name ?? 'N/A',
                'submitted_by_role' => $this->formatOfficialPosition($document->creator?->officialProfile?->position),
                'status' => strtolower((string) ($document->status ?? Abyip::STATUS_PENDING)),
                'rejection_reason' => Schema::hasColumn('abyip', 'rejection_reason')
                    ? $document->rejection_reason
                    : null,
                'has_pdf' => (bool) ($document->has_pdf_flag ?? false),
                'file' => (bool) ($document->has_pdf_flag ?? false) ? url('/api/barangay-abyip/'.$document->id.'/file') : null,
                'file_url' => (bool) ($document->has_pdf_flag ?? false) ? url('/api/barangay-abyip/'.$document->id.'/file') : null,
            ])
            ->all();
    }

    /**
     * Batch resolve barangay IDs for catalog names (one query).
     *
     * @param  array<string, string>  $slugToName
     * @return array<string, int|null> slug => barangay_id
     */
    public function resolveBarangayIdsBySlug(array $slugToName): array
    {
        $out = [];
        foreach (array_keys($slugToName) as $slug) {
            $out[$slug] = null;
        }

        if ($slugToName === [] || ! Schema::hasTable('barangays')) {
            return $out;
        }

        $names = array_values(array_unique(array_filter($slugToName)));
        $rows = Barangay::query()
            ->whereIn('name', $names)
            ->get(['id', 'name']);

        $nameToId = [];
        foreach ($rows as $row) {
            $nameToId[(string) $row->name] = (int) $row->id;
        }

        foreach ($slugToName as $slug => $name) {
            $out[$slug] = $nameToId[$name] ?? null;
        }

        return $out;
    }

    /**
     * Latest ABYIP status per barangay without loading pdf_data.
     *
     * @param  list<int>  $barangayIds
     * @return array<int, array{status: string, submitted_by: ?string, submitted_by_role: ?string, latest_report: ?array<string, mixed>}>
     */
    public function resolveAbyipStatusMap(array $barangayIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $barangayIds))));
        $empty = [
            'status' => 'not_submitted',
            'submitted_by' => null,
            'submitted_by_role' => null,
            'latest_report' => null,
        ];
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = $empty;
        }

        if ($ids === [] || ! Schema::hasTable('abyip')) {
            return $out;
        }

        $currentYear = (int) now()->format('Y');
        $documents = Abyip::query()
            ->documents()
            ->with(['creator:id,name', 'creator.officialProfile:id,user_id,position'])
            ->whereIn('barangay_id', $ids)
            ->orderByDesc('created_at')
            ->get($this->abyipDocumentListColumns());

        $byBarangay = [];
        foreach ($documents as $document) {
            $bid = (int) $document->barangay_id;
            $byBarangay[$bid] = $byBarangay[$bid] ?? [];
            $byBarangay[$bid][] = $document;
        }

        foreach ($byBarangay as $bid => $docs) {
            $currentYearDoc = null;
            foreach ($docs as $doc) {
                if ((int) ($doc->fiscal_year ?? 0) === $currentYear) {
                    $currentYearDoc = $doc;
                    break;
                }
            }
            $latest = $currentYearDoc ?? $docs[0];
            $status = strtolower((string) ($latest->status ?? Abyip::STATUS_PENDING));
            if (! in_array($status, [Abyip::STATUS_PENDING, Abyip::STATUS_APPROVED, Abyip::STATUS_REJECTED], true)) {
                $status = Abyip::STATUS_PENDING;
            }

            $report = [
                'id' => $latest->id,
                'name' => $latest->document_title ?: 'ABYIP '.$latest->fiscal_year,
                'title' => $latest->document_title ?: 'ABYIP '.$latest->fiscal_year,
                'fiscal_year' => $latest->fiscal_year,
                'date_submitted' => $latest->created_at?->toDateTimeString(),
                'submitted_by' => $latest->creator?->name ?? 'N/A',
                'submitted_by_role' => $this->formatOfficialPosition($latest->creator?->officialProfile?->position),
                'status' => $status,
            ];

            $out[$bid] = [
                'status' => $status,
                'submitted_by' => $report['submitted_by'],
                'submitted_by_role' => $report['submitted_by_role'],
                'latest_report' => $report,
            ];
        }

        return $out;
    }

    /**
     * @return array{status: string, submitted_by: ?string, submitted_by_role: ?string, latest_report: ?array<string, mixed>}
     */
    public function resolveAbyipStatus(?int $barangayId): array
    {
        if ($barangayId === null) {
            return [
                'status' => 'not_submitted',
                'submitted_by' => null,
                'submitted_by_role' => null,
                'latest_report' => null,
            ];
        }

        $map = $this->resolveAbyipStatusMap([$barangayId]);

        return $map[$barangayId] ?? [
            'status' => 'not_submitted',
            'submitted_by' => null,
            'submitted_by_role' => null,
            'latest_report' => null,
        ];
    }

    public function countBarangaysWithAbyipSubmission(?string $dateFrom = null, ?string $dateTo = null): int
    {
        if (! Schema::hasTable('abyip')) {
            return 0;
        }

        $query = Abyip::query()
            ->documents()
            ->whereNotNull('barangay_id');

        $this->applyDashboardAbyipFilters($query, $dateFrom, $dateTo);

        return (int) $query
            ->selectRaw('count(distinct barangay_id) as aggregate')
            ->value('aggregate');
    }

    /**
     * @return array{
     *     stats: array{submitted: int, pending: int, approved: int, rejected: int, fiscal_year: int, fiscal_year_label: string, total_barangays: int},
     *     recent: list<array{barangay: string, slug: ?string, detail_url: string, title: string, fiscal_year: int, submitted_by: string, submitted_by_role: ?string, status: string, date_submitted: ?string}>
     * }
     */
    public function dashboardAbyipOverview(int $limit = 8, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$fromYear, $toYear] = $this->yearsFromDateRange($dateFrom, $dateTo);
        $yearLabel = $fromYear === $toYear ? 'CY '.$fromYear : 'CY '.$fromYear.'–'.$toYear;

        $emptyStats = [
            'submitted' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'fiscal_year' => $toYear,
            'fiscal_year_label' => $yearLabel,
            'total_barangays' => count($this->slugToNameMap()),
        ];

        if (! Schema::hasTable('abyip')) {
            return ['stats' => $emptyStats, 'recent' => []];
        }

        $nameToSlug = array_flip($this->slugToNameMap());

        $documentsQuery = Abyip::query()
            ->documents()
            ->with([
                'barangay:id,name',
                'creator:id,name',
                'creator.officialProfile:id,user_id,position',
            ])
            ->whereNotNull('barangay_id');

        $this->applyDashboardAbyipFilters($documentsQuery, $dateFrom, $dateTo);

        // The dashboard only needs status and labels. pdf_data is a stored PDF
        // and is not displayed on this card.
        $documents = $documentsQuery
            ->select([
                'id',
                'barangay_id',
                'created_by',
                'fiscal_year',
                'document_title',
                'status',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->get();

        $latestByBarangay = $documents->unique('barangay_id')->values();

        $stats = [
            'submitted' => $latestByBarangay->count(),
            'pending' => $latestByBarangay->filter(fn (Abyip $doc) => strtolower((string) ($doc->status ?? '')) === Abyip::STATUS_PENDING)->count(),
            'approved' => $latestByBarangay->filter(fn (Abyip $doc) => strtolower((string) ($doc->status ?? '')) === Abyip::STATUS_APPROVED)->count(),
            'rejected' => $latestByBarangay->filter(fn (Abyip $doc) => strtolower((string) ($doc->status ?? '')) === Abyip::STATUS_REJECTED)->count(),
            'fiscal_year' => $toYear,
            'fiscal_year_label' => $yearLabel,
            'total_barangays' => count($this->slugToNameMap()),
        ];

        $recent = $latestByBarangay
            ->take($limit)
            ->map(function (Abyip $document) use ($nameToSlug): array {
                $barangayName = $document->barangay?->name ?? 'Unknown';
                $status = strtolower((string) ($document->status ?? Abyip::STATUS_PENDING));

                if (! in_array($status, [Abyip::STATUS_PENDING, Abyip::STATUS_APPROVED, Abyip::STATUS_REJECTED], true)) {
                    $status = Abyip::STATUS_PENDING;
                }

                return [
                    'barangay' => $barangayName,
                    'slug' => $nameToSlug[$barangayName] ?? null,
                    'detail_url' => ($nameToSlug[$barangayName] ?? null)
                        ? route('barangay-monitoring.show', ['barangay' => $nameToSlug[$barangayName]])
                        : route('barangay-monitoring'),
                    'title' => filled($document->document_title) ? (string) $document->document_title : 'ABYIP '.$document->fiscal_year,
                    'fiscal_year' => (int) $document->fiscal_year,
                    'submitted_by' => $document->creator?->name ?? 'N/A',
                    'submitted_by_role' => $this->formatOfficialPosition($document->creator?->officialProfile?->position),
                    'status' => $status,
                    'date_submitted' => $document->created_at?->timezone(config('app.timezone', 'Asia/Manila'))->format('M j, Y'),
                ];
            })
            ->all();

        return [
            'stats' => $stats,
            'recent' => $recent,
        ];
    }

    /**
     * @param  Builder<Abyip>  $query
     */
    private function applyDashboardAbyipFilters($query, ?string $dateFrom, ?string $dateTo): void
    {
        [$from, $to] = $this->normalizeDashboardDateRange($dateFrom, $dateTo);
        [$fromYear, $toYear] = $this->yearsFromDateRange($from, $to);

        $query->whereBetween('fiscal_year', [$fromYear, $toYear]);

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function normalizeDashboardDateRange(?string $dateFrom, ?string $dateTo): array
    {
        $from = trim((string) $dateFrom);
        $to = trim((string) $dateTo);

        if ($from === '') {
            $from = null;
        }
        if ($to === '') {
            $to = null;
        }

        if ($from !== null && $to !== null && $from > $to) {
            return [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function yearsFromDateRange(?string $dateFrom, ?string $dateTo): array
    {
        [$from, $to] = $this->normalizeDashboardDateRange($dateFrom, $dateTo);
        $fallbackYear = (int) now()->format('Y');
        $fromYear = $from !== null ? (int) substr($from, 0, 4) : $fallbackYear;
        $toYear = $to !== null ? (int) substr($to, 0, 4) : $fromYear;

        if ($from === null && $to === null) {
            return [$fallbackYear, $fallbackYear];
        }

        if ($fromYear > $toYear) {
            return [$toYear, $fromYear];
        }

        return [$fromYear, $toYear];
    }

    private function formatOfficialPosition(?string $position): ?string
    {
        if ($position === null || trim($position) === '') {
            return null;
        }

        return match ($position) {
            'Chairperson' => 'SK Chairperson',
            'Secretary' => 'SK Secretary',
            'Treasurer' => 'SK Treasurer',
            'Kagawad' => 'SK Kagawad',
            default => $position,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getApprovedAccomplishments(?int $barangayId): array
    {
        if ($barangayId === null || ! Schema::hasTable('report_management')) {
            return [];
        }

        return ReportManagement::query()
            ->where('barangay_id', $barangayId)
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ReportManagement $report) => [
                'id' => $report->id,
                'title' => $report->program_name,
                'activity' => $report->activity_name,
                'program_code' => $report->program_code,
                'description' => $report->activity_name,
                'sector' => $report->program_code,
                'timeline' => $report->created_at?->toDateString(),
                'start_date' => $report->created_at?->toDateTimeString(),
                'year' => (int) $report->created_at?->format('Y'),
                'term' => $this->skTermForYear((int) $report->created_at?->format('Y')),
                'status' => 'Approved',
                'file_url' => route('api.reports.download', ['id' => $report->id]),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function accomplishmentYears(?int $barangayId): array
    {
        return collect($this->getApprovedAccomplishments($barangayId))
            ->pluck('year')
            ->unique()
            ->sortDesc()
            ->map(fn ($year) => (string) $year)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function accomplishmentTerms(?int $barangayId): array
    {
        return collect($this->getApprovedAccomplishments($barangayId))
            ->pluck('term')
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function skTermForYear(int $year): string
    {
        $base = 2022;
        $offset = max(0, $year - $base);
        $termStart = $base + (int) (floor($offset / 3) * 3);

        return $termStart.'-'.($termStart + 2);
    }

    /**
     * Columns used by barangay-monitoring ABYIP listings.
     * Omits rejection_reason when the production schema has not been migrated yet.
     *
     * @return list<string>
     */
    private function abyipDocumentListColumns(): array
    {
        $columns = [
            'id',
            'barangay_id',
            'document_title',
            'fiscal_year',
            'status',
            'created_by',
            'created_at',
        ];

        if (Schema::hasColumn('abyip', 'rejection_reason')) {
            $columns[] = 'rejection_reason';
        }

        return $columns;
    }
}
