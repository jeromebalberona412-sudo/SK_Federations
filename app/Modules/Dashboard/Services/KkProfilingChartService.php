<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KkProfilingChartService
{
    /**
     * @return array<string, mixed>
     */
    public function getChartData(User $user, Request $request): array
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return $this->emptyPayload('monthly');
        }

        $tenantId = (int) $user->tenant_id;
        $barangayId = $request->input('barangay_id', 'all');
        $period = $request->input('period', 'monthly') === 'weekly' ? 'weekly' : 'monthly';
        $year = (int) $request->input('year', now()->year);
        $month = max(1, min(12, (int) $request->input('month', now()->month)));

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
