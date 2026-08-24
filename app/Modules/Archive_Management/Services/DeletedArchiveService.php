<?php

namespace App\Modules\Archive_Management\Services;

use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Services\AccountService;
use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeletedArchiveService
{
    public function __construct(private readonly AccountService $accountService)
    {
    }

    /**
     * @return array{data: list<array<string, mixed>>, stats: array{total: int, today: int, month: int}, filters: array<string, list<string>>}
     */
    public function listDeletedAccounts(string $role, int $tenantId, Request $request): array
    {
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
            'filters' => $this->buildFilterOptions($all),
        ];
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
        ];
    }

    private function formatTermLabel(?OfficialTerm $term): string
    {
        if (! $term?->term_start || ! $term?->term_end) {
            return '—';
        }

        return $term->term_start->format('Y').'-'.$term->term_end->format('Y');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $records
     * @return array<string, list<string>>
     */
    private function buildFilterOptions($records): array
    {
        $barangays = $records
            ->map(fn (User $user) => $user->barangay?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $positions = $records
            ->map(fn (User $user) => $user->officialProfile?->position)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

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
