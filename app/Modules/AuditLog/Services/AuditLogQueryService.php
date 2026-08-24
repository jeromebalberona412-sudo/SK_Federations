<?php

namespace App\Modules\AuditLog\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\AuditLog\Models\AdminActivityLog;
use App\Modules\AuditLog\Support\AuditLogPresenter;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditLogQueryService
{
    public const MODULE_OPTIONS = [
        'authentication' => 'Authentication',
        'accounts' => 'Accounts',
        'archive_management' => 'Archive Management',
        'barangay_logos' => 'Barangay Logos',
        'security' => 'Security',
        'announcements' => 'Announcements',
        'community_feeds' => 'Community Feed',
        'programs' => 'Programs',
        'surveys' => 'Surveys',
        'scholarships' => 'Scholarships',
        'sports' => 'Sports',
        'settings' => 'System Settings',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, ?int $tenantId, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = $this->baseQuery($tenantId);
        $this->applyFilters($query, $filters);

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function (AdminActivityLog $log) {
            return AuditLogPresenter::toArray($log);
        });

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function exportRows(array $filters, ?int $tenantId, int $limit = 10000): Collection
    {
        $query = $this->baseQuery($tenantId);
        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AdminActivityLog $log) => AuditLogPresenter::toArray($log));
    }

    /**
     * @return array<string, int>
     */
    public function stats(?int $tenantId): array
    {
        $base = AdminActivityLog::query();
        $this->applyTenantScope($base, $tenantId);

        $today = now()->startOfDay();

        $todayQuery = AdminActivityLog::query();
        $this->applyTenantScope($todayQuery, $tenantId);
        $todayQuery->where('created_at', '>=', $today);

        $securityQuery = AdminActivityLog::query();
        $this->applyTenantScope($securityQuery, $tenantId);
        $securityQuery->whereIn('event_type', AdminActivityLog::securityEventTypes());

        $activeUsersQuery = AdminActivityLog::query();
        $this->applyTenantScope($activeUsersQuery, $tenantId);
        $activeUsersQuery
            ->where('event_type', AdminActivityLog::EVENT_LOGIN_SUCCESS)
            ->where('created_at', '>=', $today)
            ->whereNotNull('user_id');

        return [
            'total_logs' => (int) $base->count(),
            'today_activities' => (int) $todayQuery->count(),
            'security_events' => (int) $securityQuery->count(),
            'active_users_today' => (int) $activeUsersQuery->distinct()->count('user_id'),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function recentActivity(?int $tenantId, int $limit = 10): Collection
    {
        return $this->baseQuery($tenantId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AdminActivityLog $log) => AuditLogPresenter::toArray($log));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function dashboardRecentActivity(?int $tenantId, int $limit = 10): Collection
    {
        return $this->recentActivity($tenantId, $limit)->map(function (array $row) {
            $outcome = strtolower((string) ($row['metadata']['outcome'] ?? ''));

            if ($outcome === '') {
                $outcome = $row['is_security'] ? 'failure' : 'success';
            }

            if (! in_array($outcome, ['success', 'warning', 'failure', 'failed', 'blocked'], true)) {
                $outcome = 'success';
            }

            if ($outcome === 'failed' || $outcome === 'blocked') {
                $outcome = 'failure';
            }

            return [
                'id' => $row['id'],
                'date' => $row['created_date'],
                'time' => $row['created_time'],
                'actor' => $row['user_name'],
                'email' => $row['user_email'] ?? '-',
                'event' => $row['summary'],
                'outcome' => $outcome,
                'ipAddress' => $row['ip_address'],
                'device' => $row['device_type'] ?? 'Unknown',
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(?int $tenantId): array
    {
        $logs = AdminActivityLog::query();
        $this->applyTenantScope($logs, $tenantId);

        $eventTypes = (clone $logs)
            ->select('event_type')
            ->whereNotNull('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type')
            ->filter(function (mixed $type): bool {
                $normalized = strtolower((string) $type);

                return $normalized !== '' && ! str_contains($normalized, 'upload');
            })
            ->values();

        $dateBoundsQuery = clone $logs;
        $oldestLog = (clone $dateBoundsQuery)->orderBy('created_at')->value('created_at');
        $newestLog = (clone $dateBoundsQuery)->orderByDesc('created_at')->value('created_at');
        $minDate = $oldestLog ? Carbon::parse($oldestLog)->toDateString() : '';
        $maxDate = $newestLog ? Carbon::parse($newestLog)->toDateString() : '';

        $actions = (clone $logs)
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values();

        $usersQuery = User::query()
            ->select(['users.id', 'users.name', 'users.email', 'users.role'])
            ->whereIn('users.id', function ($sub) use ($tenantId) {
                $sub->select('user_id')
                    ->from('admin_activity_logs')
                    ->whereNotNull('user_id');

                if ($tenantId !== null) {
                    $sub->where(function ($tenantQuery) use ($tenantId) {
                        $tenantQuery->where('tenant_id', $tenantId)
                            ->orWhereNull('tenant_id');
                    });
                }
            })
            ->orderBy('users.name');

        $users = $usersQuery->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => AuditLogPresenter::resolveUserName($user->name, $user->email),
            'email' => $user->email,
            'role' => $user->role,
            'role_label' => AuditLogPresenter::roleLabel($user->role),
        ]);

        $roles = collect([
            ['value' => User::ROLE_SK_FED, 'label' => 'SK Federation'],
            ['value' => User::ROLE_SK_OFFICIAL, 'label' => 'SK Official'],
            ['value' => User::ROLE_USER, 'label' => 'Kabataan'],
        ]);

        $barangaysQuery = Barangay::query()->orderBy('name');
        if ($tenantId !== null) {
            $barangaysQuery->where('tenant_id', $tenantId);
        }

        $barangays = $barangaysQuery
            ->get(['id', 'name'])
            ->map(fn (Barangay $barangay) => [
                'id' => $barangay->id,
                'name' => $barangay->name,
            ]);

        $modules = collect(self::MODULE_OPTIONS)->map(fn (string $label, string $value) => [
            'value' => $value,
            'label' => $label,
        ])->values();

        return [
            'users' => $users,
            'roles' => $roles,
            'barangays' => $barangays,
            'event_types' => $eventTypes,
            'actions' => $actions,
            'modules' => $modules,
            'date_bounds' => [
                'min' => $minDate,
                'max' => $maxDate,
            ],
        ];
    }

    protected function baseQuery(?int $tenantId): Builder
    {
        $query = AdminActivityLog::query()->with(['user.barangay']);
        $this->applyTenantScope($query, $tenantId);

        return $query;
    }

    /**
     * @param  Builder<AdminActivityLog>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $userId = $filters['user_id'] ?? null;
        $role = trim((string) ($filters['role'] ?? ''));
        $barangayId = $filters['barangay_id'] ?? null;
        $eventType = trim((string) ($filters['event_type'] ?? ($filters['event'] ?? '')));
        $action = trim((string) ($filters['action'] ?? ''));
        $module = trim((string) ($filters['module'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search) {
                $like = '%'.$search.'%';

                $inner->where('event_type', 'ilike', $like)
                    ->orWhere('action', 'ilike', $like)
                    ->orWhere('entity_type', 'ilike', $like)
                    ->orWhere('entity_id', 'ilike', $like)
                    ->orWhere('ip_address', 'ilike', $like)
                    ->orWhereRaw('metadata::text ILIKE ?', [$like])
                    ->orWhereHas('user', function (Builder $userQuery) use ($like) {
                        $userQuery->where('name', 'ilike', $like)
                            ->orWhere('email', 'ilike', $like)
                            ->orWhereHas('barangay', fn (Builder $barangayQuery) => $barangayQuery->where('name', 'ilike', $like));
                    });
            });
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }

        if ($role !== '') {
            $query->whereHas('user', function (Builder $userQuery) use ($role) {
                if ($role === User::ROLE_SK_FED || $role === User::ROLE_ADMIN) {
                    $userQuery->whereIn('role', [User::ROLE_SK_FED, User::ROLE_ADMIN, 'super_admin', 'SUPER_ADMIN']);
                } else {
                    $userQuery->where('role', $role);
                }
            });
        }

        if ($barangayId !== null && $barangayId !== '') {
            $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('barangay_id', (int) $barangayId));
        }

        if ($eventType !== '') {
            $query->where('event_type', $eventType);
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($module !== '') {
            $query->where(function (Builder $inner) use ($module) {
                $inner->whereRaw("metadata->>'module' = ?", [$module])
                    ->orWhere('event_type', 'like', $module.'.%');
            });
        }
    }

    /**
     * @param  Builder<AdminActivityLog>  $query
     */
    protected function applyTenantScope(Builder $query, ?int $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }

        $query->where(function (Builder $tenantQuery) use ($tenantId) {
            $tenantQuery->where('tenant_id', $tenantId)
                ->orWhereNull('tenant_id');
        });
    }
}
