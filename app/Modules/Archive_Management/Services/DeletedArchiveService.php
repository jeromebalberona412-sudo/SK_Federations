<?php

namespace App\Modules\Archive_Management\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Services\AccountService;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DeletedArchiveService
{
    public const RETENTION_DAYS = 30;

    public function __construct(private readonly AccountService $accountService) {}

    /**
     * Permanently remove soft-deleted SK Officials past the retention window.
     */
    public function purgeExpiredDeletedOfficials(?int $tenantId = null): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        $query = User::onlyTrashed()
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->where('deleted_at', '<=', $cutoff);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $purged = 0;
        foreach ($query->get() as $user) {
            $this->accountService->permanentlyDeleteTrashedAccount($user);
            $purged++;
        }

        return $purged;
    }

    /**
     * @return array{data: list<array<string, mixed>>, stats: array{total: int, today: int, month: int}, filters: array<string, list<string>>}
     */
    public function listDeletedAccounts(string $role, int $tenantId, Request $request): array
    {
        if ($role === User::ROLE_SK_OFFICIAL) {
            $this->purgeExpiredDeletedOfficials($tenantId);
        }

        $query = User::onlyTrashed()
            ->with(['barangay', 'officialProfile.latestTerm'])
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->orderByDesc('deleted_at');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereHas('officialProfile', function ($profileQuery) use ($search) {
                        $profileQuery->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('middle_name', 'ilike', "%{$search}%")
                            ->orWhere('position', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('barangay', function ($barangayQuery) use ($search) {
                        $barangayQuery->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        $filter = (string) $request->input('filter', 'all');
        if ($filter === 'today') {
            $query->whereDate('deleted_at', now()->toDateString());
        } elseif ($filter === 'week') {
            $query->where('deleted_at', '>=', now()->startOfWeek());
        } elseif ($filter === 'month') {
            $query->where('deleted_at', '>=', now()->startOfMonth());
        }

        if ($year = $request->input('year')) {
            if ($year !== 'all' && is_numeric($year)) {
                $query->whereYear('deleted_at', (int) $year);
            }
        }

        if ($barangay = trim((string) $request->input('barangay', ''))) {
            $query->whereHas('barangay', fn ($builder) => $builder->where('name', $barangay));
        }

        if ($position = trim((string) $request->input('position', ''))) {
            $query->whereHas('officialProfile', fn ($builder) => $builder->where('position', $position));
        }

        if ($term = trim((string) $request->input('term', ''))) {
            [$startYear, $endYear] = array_pad(explode('-', $term), 2, null);
            if ($startYear && $endYear) {
                $query->whereHas('officialProfile.latestTerm', function ($builder) use ($startYear, $endYear) {
                    $builder->whereYear('term_start', (int) $startYear)
                        ->whereYear('term_end', (int) $endYear);
                });
            }
        }

        $records = $query->get();

        $all = User::onlyTrashed()
            ->with(['barangay', 'officialProfile.latestTerm'])
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->orderByDesc('deleted_at')
            ->get();

        return [
            'data' => $records->map(fn (User $user) => $this->formatDeletedAccount($user))->values()->all(),
            'stats' => [
                'total' => $all->count(),
                'today' => $all->filter(fn (User $user) => $user->deleted_at?->isToday())->count(),
                'month' => $all->filter(fn (User $user) => $user->deleted_at?->isCurrentMonth())->count(),
            ],
            'filters' => $this->buildFilterOptions($all, $tenantId, $role),
            'retention_days' => self::RETENTION_DAYS,
        ];
    }

    /**
     * Filter options for the Deleted SK Officials page (all barangays + positions).
     *
     * @return array<string, list<string>>
     */
    public function filterCatalog(int $tenantId, string $role = User::ROLE_SK_OFFICIAL): array
    {
        $all = User::onlyTrashed()
            ->with(['barangay', 'officialProfile.latestTerm'])
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->get();

        return $this->buildFilterOptions($all, $tenantId, $role);
    }

    public function restoreAccount(int $accountId, string $role, User $admin): User
    {
        $account = User::onlyTrashed()
            ->with(['officialProfile.latestTerm'])
            ->where('tenant_id', $admin->tenant_id)
            ->where('role', $role)
            ->whereKey($accountId)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'account' => 'Deleted account not found or already restored.',
            ]);
        }

        return $this->accountService->restoreAccount($account, $admin);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDeletedAccount(User $user): array
    {
        $profile = $user->officialProfile;
        $term = $profile?->latestTerm;
        $deletedAt = $user->deleted_at;
        $daysRemaining = $this->daysRemaining($deletedAt);

        return [
            'id' => $user->id,
            'firstName' => $profile?->first_name ?? '',
            'middleName' => $profile?->middle_name ?? '',
            'lastName' => $profile?->last_name ?? '',
            'suffix' => $profile?->suffix ?? '',
            'position' => $profile?->position ?? '—',
            'barangay' => $user->barangay?->name ?? '—',
            'municipality' => $profile?->municipality ?? 'Santa Cruz',
            'province' => $profile?->province ?? 'Laguna',
            'region' => $profile?->region ?? 'IV-A CALABARZON',
            'contactNumber' => $profile?->contact_number ?? '—',
            'email' => $user->email,
            'sex' => $profile?->sex ?? '—',
            'dateOfBirth' => $profile?->date_of_birth?->format('M d, Y') ?? '—',
            'age' => $profile?->age,
            'emailVerification' => $user->email_verified_at?->format('m/d/Y h:i A') ?? 'Not Verified',
            'termStart' => $term?->term_start?->format('M d, Y') ?? '—',
            'termEnd' => $term?->term_end?->format('M d, Y') ?? '—',
            'term' => $this->formatTermLabel($term),
            'accountStatus' => $user->status ?? '—',
            'termStatus' => $term?->status ?? '—',
            'deletedDate' => $deletedAt?->format('M d, Y') ?? '—',
            'deletedTime' => $deletedAt?->format('h:i A') ?? '—',
            'deleted_at' => $deletedAt?->toIso8601String(),
            'days_remaining' => $daysRemaining,
            'auto_delete_label' => $daysRemaining <= 0
                ? 'Deleting soon'
                : 'Auto delete in '.$daysRemaining.' day'.($daysRemaining === 1 ? '' : 's'),
        ];
    }

    private function daysRemaining(?Carbon $deletedAt): int
    {
        if ($deletedAt === null) {
            return self::RETENTION_DAYS;
        }

        $expiresAt = $deletedAt->copy()->addDays(self::RETENTION_DAYS);

        if ($expiresAt->lte(now())) {
            return 0;
        }

        $secondsLeft = $expiresAt->getTimestamp() - now()->getTimestamp();

        return (int) max(1, (int) ceil($secondsLeft / 86400));
    }

    private function formatTermLabel(?OfficialTerm $term): string
    {
        if (! $term?->term_start || ! $term?->term_end) {
            return '—';
        }

        return $term->term_start->format('Y').'-'.$term->term_end->format('Y');
    }

    /**
     * @param  Collection<int, User>  $records
     * @return array<string, list<string>>
     */
    private function buildFilterOptions($records, int $tenantId, string $role): array
    {
        $barangays = Barangay::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($barangays === []) {
            $barangays = $records
                ->map(fn (User $user) => $user->barangay?->name)
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        $positions = $role === User::ROLE_SK_OFFICIAL
            ? array_values(OfficialProfile::OFFICIAL_POSITIONS)
            : array_values(OfficialProfile::FEDERATION_POSITIONS);

        $fromDeleted = $records
            ->map(fn (User $user) => $user->officialProfile?->position)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $positions = array_values(array_unique(array_merge($positions, $fromDeleted)));
        sort($positions);

        $terms = $records
            ->map(fn (User $user) => $this->formatTermLabel($user->officialProfile?->latestTerm))
            ->filter(fn (string $term) => $term !== '—')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $years = $records
            ->map(fn (User $user) => $user->deleted_at?->format('Y'))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return [
            'barangays' => $barangays,
            'positions' => $positions,
            'terms' => $terms,
            'years' => $years,
        ];
    }
}
