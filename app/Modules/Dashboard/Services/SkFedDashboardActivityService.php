<?php

namespace App\Modules\Dashboard\Services;

use App\Models\SkFedActivity;
use App\Models\SkFederationCalendarNote;
use App\Modules\Shared\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class SkFedDashboardActivityService
{
  public function recentActivity(?int $tenantId, int $limit = 12): array
  {
    if (! Schema::hasTable('sk_fed_activities')) {
      return [];
    }

    return $this->baseQuery($tenantId)
      ->limit($limit)
      ->get()
      ->map(fn (SkFedActivity $activity) => $this->formatActivity($activity))
      ->all();
  }

  /**
   * @return array{data: list<array<string, string>>, meta: array<string, int>}
   */
  public function paginatedActivities(?int $tenantId, int $page = 1, int $perPage = 20): array
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
    $paginator = $this->baseQuery($tenantId)
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

  public function upcomingCalendarNotes(int $limit = 6): array
  {
    if (! Schema::hasTable('sk_federations_calendar')) {
      return [];
    }

    return SkFederationCalendarNote::query()
      ->whereDate('note_date', '>', now()->toDateString())
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
   * @return \Illuminate\Database\Eloquent\Builder<SkFedActivity>
   */
  private function baseQuery(?int $tenantId)
  {
    return SkFedActivity::query()
      ->with(['user.officialProfile'])
      ->when($tenantId !== null, function ($query) use ($tenantId) {
        $query->whereHas('user', fn ($userQuery) => $userQuery->where('tenant_id', $tenantId));
      })
      ->orderByDesc('created_at');
  }

  /**
   * @return array{text: string, who: string, position: string, time: string}
   */
  private function formatActivity(SkFedActivity $activity): array
  {
    $user = $activity->user;
    $who = $user?->name ? strtoupper((string) $user->name) : 'SK Federation';
    $position = $user?->officialProfile?->federation_position
      ?? ($user?->hasRole(User::ROLE_SK_FED) ? 'SK Federation' : 'Admin');

    return [
      'text' => $activity->description,
      'who' => $who,
      'position' => (string) $position,
      'time' => $activity->created_at?->diffForHumans() ?? '—',
    ];
  }
}
