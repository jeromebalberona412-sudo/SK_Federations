<?php

namespace App\Modules\Dashboard\Services;

use App\Models\SkFedActivity;
use App\Models\SkFederationCalendarNote;
use App\Modules\Shared\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class SkFedDashboardActivityService
{
    public function recentActivity(?int $tenantId, int $limit = 12, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (! Schema::hasTable('sk_fed_activities')) {
            return [];
        }

        return $this->baseQuery($tenantId, $dateFrom, $dateTo)
            ->limit($limit)
            ->get()
            ->map(fn (SkFedActivity $activity) => $this->formatActivity($activity))
            ->all();
    }

    /**
     * @return array{data: list<array<string, string>>, meta: array<string, int>}
     */
    public function paginatedActivities(
        ?int $tenantId,
        int $page = 1,
        int $perPage = 20,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array
    {
        if (! Schema::hasTable('sk_fed_activities')) {
            return [
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'from' => 0,
                    'to' => 0,
                ],
            ];
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->baseQuery($tenantId, $dateFrom, $dateTo)
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())
                ->map(fn (SkFedActivity $activity) => $this->formatActivity($activity))
                ->all(),
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
        ];
    }

    public function upcomingCalendarNotes(int $limit = 6, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (! Schema::hasTable('sk_federations_calendar')) {
            return [];
        }

        $from = trim((string) $dateFrom);
        $to = trim((string) $dateTo);
        $today = now()->toDateString();

        $query = SkFederationCalendarNote::query();

        if ($from !== '' && $to !== '') {
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            $query->where(function ($rangeQuery) use ($from, $to, $today): void {
                $rangeQuery->whereDate('note_date', '>=', $from)
                    ->whereDate('note_date', '<=', $to);

                if ($to >= $today) {
                    $rangeQuery->orWhereDate('note_date', '>', $today);
                }
            });
        } else {
            $query->whereDate('note_date', '>', $today);
        }

        return $query
            ->orderBy('note_date')
            ->limit($limit)
            ->get()
            ->map(function (SkFederationCalendarNote $note) {
                $date = $note->note_date;

                return [
                    'day' => $date->format('j'),
                    'month_label' => strtoupper($date->format('M')),
                    'title' => $note->title,
                ];
            })
            ->all();
    }

    /**
     * @return array{title: string, date_label: string}|null
     */
    public function todayCalendarNote(): ?array
    {
        if (! Schema::hasTable('sk_federations_calendar')) {
            return null;
        }

        $note = SkFederationCalendarNote::query()
            ->whereDate('note_date', now()->toDateString())
            ->first();

        if (! $note) {
            return null;
        }

        return [
            'title' => $note->title,
            'date_label' => now()->format('F j'),
        ];
    }

    /**
     * @return Builder<SkFedActivity>
     */
    private function baseQuery(?int $tenantId, ?string $dateFrom = null, ?string $dateTo = null)
    {
        $query = SkFedActivity::query()
            ->with(['user.officialProfile'])
            ->when($tenantId !== null, function ($query) use ($tenantId) {
                $query->whereHas('user', fn ($userQuery) => $userQuery->where('tenant_id', $tenantId));
            })
            ->orderByDesc('created_at');

        $from = trim((string) $dateFrom);
        $to = trim((string) $dateTo);
        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * @return array{id: string, text: string, who: string, position: string, time: string}
     */
    private function formatActivity(SkFedActivity $activity): array
    {
        $user = $activity->user;
        $who = $user?->name ? strtoupper((string) $user->name) : 'SK Federation';
        $position = $user?->officialProfile?->federation_position
          ?? ($user?->hasRole(User::ROLE_SK_FED) ? 'SK Federation' : 'Admin');

        return [
            'id' => (string) $activity->id,
            'text' => $activity->description,
            'who' => $who,
            'position' => (string) $position,
            'time' => $activity->created_at?->diffForHumans() ?? '—',
        ];
    }
}
