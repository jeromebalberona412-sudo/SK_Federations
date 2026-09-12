<?php

namespace App\Modules\Archive_Management\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Services\AccountService;
use App\Modules\Archive_Management\Models\DeletedSkOfficial;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletedArchiveService
{
    public function __construct(private readonly AccountService $accountService) {}

    /**
     * Intentionally disabled — archived SK Officials are retained indefinitely.
     */
    public function purgeExpiredDeletedOfficials(?int $tenantId = null): int
    {
        return 0;
    }

    /**
     * @return array{data: list<array<string, mixed>>, stats: array{total: int, today: int, month: int}, filters: array<string, list<string>>}
     */
    public function listDeletedAccounts(string $role, int $tenantId, Request $request): array
    {
        if ($role !== User::ROLE_SK_OFFICIAL) {
            return [
                'data' => [],
                'stats' => ['total' => 0, 'today' => 0, 'month' => 0],
                'filters' => $this->buildFilterOptions(collect(), $tenantId, $role),
            ];
        }

        $query = DeletedSkOfficial::query()
            ->activeArchive()
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->orderByDesc('deleted_at')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('middle_name', 'ilike', "%{$search}%")
                    ->orWhere('position', 'ilike', "%{$search}%")
                    ->orWhere('barangay_name', 'ilike', "%{$search}%");
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
            $query->where('barangay_name', $barangay);
        }

        if ($position = trim((string) $request->input('position', ''))) {
            $query->where('position', $position);
        }

        if ($term = trim((string) $request->input('term', ''))) {
            $query->where('term_label', $term);
        }

        $records = $query->get();

        $all = DeletedSkOfficial::query()
            ->activeArchive()
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->orderByDesc('deleted_at')
            ->get();

        return [
            'data' => $records->map(fn (DeletedSkOfficial $row) => $this->formatDeletedAccount($row))->values()->all(),
            'stats' => [
                'total' => $all->count(),
                'today' => $all->filter(fn (DeletedSkOfficial $row) => $row->deleted_at?->isToday())->count(),
                'month' => $all->filter(fn (DeletedSkOfficial $row) => $row->deleted_at?->isCurrentMonth())->count(),
            ],
            'filters' => $this->buildFilterOptions($all, $tenantId, $role),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function filterCatalog(int $tenantId, string $role = User::ROLE_SK_OFFICIAL): array
    {
        $all = DeletedSkOfficial::query()
            ->activeArchive()
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->get();

        return $this->buildFilterOptions($all, $tenantId, $role);
    }

    public function restoreAccount(int $archiveOrUserId, string $role, User $admin): User
    {
        if ($role !== User::ROLE_SK_OFFICIAL) {
            throw ValidationException::withMessages([
                'account' => 'Only deleted SK Officials can be restored from this archive.',
            ]);
        }

        $archive = DeletedSkOfficial::query()
            ->activeArchive()
            ->where('tenant_id', $admin->tenant_id)
            ->where('role', $role)
            ->where(function ($query) use ($archiveOrUserId) {
                $query->whereKey($archiveOrUserId)
                    ->orWhere('original_user_id', $archiveOrUserId);
            })
            ->orderByDesc('id')
            ->first();

        if (! $archive) {
            throw ValidationException::withMessages([
                'account' => 'Deleted account not found or already restored.',
            ]);
        }

        $originalUserId = (int) ($archive->original_user_id ?? 0);
        if ($originalUserId <= 0) {
            throw ValidationException::withMessages([
                'account' => 'This archived account can no longer be restored because the original user record is unavailable.',
            ]);
        }

        $user = User::onlyTrashed()
            ->where('tenant_id', $admin->tenant_id)
            ->where('role', $role)
            ->whereKey($originalUserId)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'account' => 'Deleted account not found or already restored.',
            ]);
        }

        return DB::transaction(function () use ($user, $admin, $archive) {
            $restored = $this->accountService->restoreAccount($user, $admin);
            $archive->forceFill([
                'restored_at' => now(),
            ])->save();

            return $restored;
        });
    }

    /**
     * Snapshot an SK Official into the dedicated archive table.
     */
    public function archiveOfficial(User $target, User $admin, ?string $reason = null): DeletedSkOfficial
    {
        $target->loadMissing(['officialProfile.latestTerm', 'barangay']);

        $profile = $target->officialProfile;
        $term = $profile?->latestTerm;
        $now = now();

        return DeletedSkOfficial::query()->create([
            'tenant_id' => (int) $target->tenant_id,
            'original_user_id' => (int) $target->id,
            'barangay_id' => $target->barangay_id !== null ? (int) $target->barangay_id : null,
            'barangay_name' => $target->barangay?->name,
            'email' => $target->email,
            'full_name' => $target->name,
            'first_name' => $profile?->first_name,
            'middle_name' => $profile?->middle_name,
            'last_name' => $profile?->last_name,
            'suffix' => $profile?->suffix,
            'contact_number' => $profile?->contact_number,
            'position' => $profile?->position,
            'federation_position' => $profile?->federation_position,
            'role' => $target->role ?: User::ROLE_SK_OFFICIAL,
            'account_status' => $target->status,
            'sex' => $profile?->sex,
            'date_of_birth' => $profile?->date_of_birth,
            'age' => $profile?->age,
            'municipality' => $profile?->municipality,
            'province' => $profile?->province,
            'region' => $profile?->region,
            'term_start' => $term?->term_start,
            'term_end' => $term?->term_end,
            'term_status' => $term?->status,
            'term_label' => $this->formatTermLabel($term?->term_start, $term?->term_end),
            'email_verified_at' => $target->email_verified_at,
            'original_created_at' => $target->created_at,
            'original_updated_at' => $target->updated_at,
            'deleted_at' => $now,
            'deleted_by' => (int) $admin->id,
            'deleted_by_name' => $admin->name,
            'deletion_reason' => $reason,
            'archived_at' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDeletedAccount(DeletedSkOfficial $row): array
    {
        $deletedAt = $row->deleted_at ?? $row->archived_at;

        return [
            'id' => $row->id,
            'archive_id' => $row->id,
            'original_user_id' => $row->original_user_id,
            'fullName' => $row->full_name ?: $this->composeName($row),
            'full_name' => $row->full_name ?: $this->composeName($row),
            'firstName' => $row->first_name ?? '',
            'middleName' => $row->middle_name ?? '',
            'lastName' => $row->last_name ?? '',
            'suffix' => $row->suffix ?? '',
            'position' => $row->position ?? '—',
            'barangay' => $row->barangay_name ?? '—',
            'municipality' => $row->municipality ?? 'Santa Cruz',
            'province' => $row->province ?? 'Laguna',
            'region' => $row->region ?? 'IV-A CALABARZON',
            'contactNumber' => $row->contact_number ?? '—',
            'email' => $row->email,
            'sex' => $row->sex ?? '—',
            'dateOfBirth' => $row->date_of_birth?->format('M d, Y') ?? '—',
            'age' => $row->age,
            'emailVerification' => $row->email_verified_at?->format('m/d/Y h:i A') ?? 'Not Verified',
            'termStart' => $row->term_start?->format('M d, Y') ?? '—',
            'termEnd' => $row->term_end?->format('M d, Y') ?? '—',
            'term' => $row->term_label ?: '—',
            'accountStatus' => $row->account_status ?? '—',
            'termStatus' => $row->term_status ?? '—',
            'deletedDate' => $deletedAt?->format('M d, Y') ?? '—',
            'deletedTime' => $deletedAt?->format('h:i A') ?? '—',
            'deleted_at' => $deletedAt?->toIso8601String(),
            'deleted_by' => $row->deleted_by_name ?? '—',
            'archived_at' => $row->archived_at?->toIso8601String(),
        ];
    }

    private function composeName(DeletedSkOfficial $row): string
    {
        $composed = collect([$row->last_name, $row->first_name, $row->middle_name, $row->suffix])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(', ');

        return $composed !== '' ? $composed : (string) ($row->email ?: '—');
    }

    private function formatTermLabel(mixed $start, mixed $end): ?string
    {
        if (! $start || ! $end) {
            return null;
        }

        $startDate = $start instanceof Carbon ? $start : Carbon::parse($start);
        $endDate = $end instanceof Carbon ? $end : Carbon::parse($end);

        return $startDate->format('Y').'-'.$endDate->format('Y');
    }

    /**
     * @param  Collection<int, DeletedSkOfficial>  $records
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
                ->map(fn (DeletedSkOfficial $row) => $row->barangay_name)
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
            ->map(fn (DeletedSkOfficial $row) => $row->position)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $positions = array_values(array_unique(array_merge($positions, $fromDeleted)));
        sort($positions);

        $terms = $records
            ->map(fn (DeletedSkOfficial $row) => $row->term_label)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $years = $records
            ->map(fn (DeletedSkOfficial $row) => ($row->deleted_at ?? $row->archived_at)?->format('Y'))
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
