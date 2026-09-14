<?php

namespace App\Modules\Deleted_SK_Officials\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Accounts\Services\SkOfficialRosterLimitsService;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Deleted_SK_Officials\Models\DeletedSkOfficial;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletedSkOfficialsService
{
    /**
     * Matches the retention window already used by Deleted Posts and Deleted Calendar Notes.
     */
    public const RETENTION_DAYS = 30;

    public function __construct(
        private readonly AuditLogInterface $auditLog,
        private readonly SkOfficialRosterLimitsService $rosterLimits,
    ) {}

    /**
     * Permanently remove archived officials whose 30-day retention window has passed.
     *
     * The users row is force-deleted, which cascades to official_profiles and
     * official_terms, then the archive snapshot itself is removed.
     */
    public function purgeExpiredDeletedOfficials(?int $tenantId = null): int
    {
        $query = DeletedSkOfficial::query()
            ->activeArchive()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<=', now()->subDays(self::RETENTION_DAYS));

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $expired = $query->get();
        $purged = 0;

        foreach ($expired as $archive) {
            try {
                DB::transaction(function () use ($archive) {
                    $user = $archive->original_user_id
                        ? User::withTrashed()->find($archive->original_user_id)
                        : null;

                    // A live user means the account was reactivated outside this archive,
                    // so only the stale snapshot is dropped.
                    if ($user !== null && $user->trashed()) {
                        $user->forceDelete();
                    }

                    $archive->delete();
                });

                $this->auditLog->log('account_permanently_deleted', null, [
                    'action' => 'purge_expired_deleted_sk_official',
                    'archive_id' => $archive->id,
                    'target_user_id' => $archive->original_user_id,
                    'target_name' => $archive->full_name,
                    'retention_days' => self::RETENTION_DAYS,
                ]);

                $purged++;
            } catch (\Throwable) {
                // A single blocked row must not stop the rest of the purge.
                continue;
            }
        }

        return $purged;
    }

    public function retentionExpiresAt(?Carbon $deletedAt): ?Carbon
    {
        return $deletedAt?->copy()->addDays(self::RETENTION_DAYS);
    }

    /**
     * Whole days left before permanent deletion, rounded up so "1" still means today.
     */
    public function daysRemaining(?Carbon $deletedAt): int
    {
        $expiresAt = $this->retentionExpiresAt($deletedAt);

        if ($expiresAt === null) {
            return self::RETENTION_DAYS;
        }

        if ($expiresAt->lte(now())) {
            return 0;
        }

        return (int) max(1, (int) ceil(($expiresAt->getTimestamp() - now()->getTimestamp()) / 86400));
    }

    public function retentionTier(int $daysRemaining): string
    {
        if ($daysRemaining <= 3) {
            return 'red';
        }

        if ($daysRemaining <= 7) {
            return 'orange';
        }

        return 'green';
    }

    /**
     * @return array{data: list<array<string, mixed>>, stats: array{total: int, today: int, month: int}, filters: array<string, mixed>}
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
            if (str_contains($term, '|')) {
                [$start, $end] = explode('|', $term, 2);
                $query->where(function ($q) use ($start, $end) {
                    $q->whereDate('term_start', $start)
                        ->whereDate('term_end', $end);
                });
            } elseif (str_contains($term, '-')) {
                [$startYear, $endYear] = explode('-', $term, 2);
                $query->where(function ($q) use ($term, $startYear, $endYear) {
                    $q->where('term_label', $term)
                        ->orWhere(function ($sub) use ($startYear, $endYear) {
                            $sub->whereYear('term_start', (int) $startYear)
                                ->whereYear('term_end', (int) $endYear);
                        });
                });
            } else {
                $query->where('term_label', $term);
            }
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
     * @return array<string, mixed>
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

    public function getDeletedAccount(int $archiveOrUserId, int $tenantId, string $role = User::ROLE_SK_OFFICIAL): ?array
    {
        $archive = DeletedSkOfficial::query()
            ->activeArchive()
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->where(function ($query) use ($archiveOrUserId) {
                $query->whereKey($archiveOrUserId)
                    ->orWhere('original_user_id', $archiveOrUserId);
            })
            ->orderByDesc('id')
            ->first();

        return $archive ? $this->formatDeletedAccount($archive) : null;
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

        if ($archive === null) {
            throw ValidationException::withMessages([
                'account' => 'Record not found in Deleted SK Officials archive.',
            ]);
        }

        return DB::transaction(function () use ($archive, $admin, $role) {
            $locked = DeletedSkOfficial::query()
                ->whereKey($archive->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->restored_at !== null) {
                throw ValidationException::withMessages([
                    'account' => 'This account has already been restored.',
                ]);
            }

            $originalUserId = (int) ($locked->original_user_id ?? 0);
            if ($originalUserId <= 0) {
                throw ValidationException::withMessages([
                    'account' => 'Original account identifier is missing. Restore cannot continue.',
                ]);
            }

            /** @var User|null $user */
            $user = User::withTrashed()->lockForUpdate()->find($originalUserId);

            if ($user === null) {
                throw ValidationException::withMessages([
                    'account' => 'Original account was not found. Restore cannot continue.',
                ]);
            }

            if (! $user->trashed()) {
                throw ValidationException::withMessages([
                    'account' => 'This account is already active in Manage Accounts.',
                ]);
            }

            $emails = collect([$user->email, $locked->email])
                ->map(static fn ($value) => strtolower(trim((string) $value)))
                ->filter(static fn (string $value): bool => $value !== '')
                ->unique()
                ->values();

            foreach ($emails as $email) {
                $emailConflict = User::query()
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $user->id)
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->exists();

                if ($emailConflict) {
                    throw ValidationException::withMessages([
                        'account' => 'Cannot restore: another active account already uses this email address.',
                    ]);
                }
            }

            $barangayId = (int) ($locked->barangay_id ?: $user->barangay_id ?: 0);
            $position = trim((string) ($locked->position ?: $user->officialProfile?->position ?: ''));
            if ($barangayId > 0 && $position !== '') {
                try {
                    $this->rosterLimits->assertRosterLimits(
                        (int) ($locked->tenant_id ?: $admin->tenant_id),
                        $barangayId,
                        $position,
                        (int) $user->id,
                        $locked->term_start?->format('Y-m-d'),
                        $locked->term_end?->format('Y-m-d'),
                    );
                } catch (ValidationException $exception) {
                    $message = (string) (collect($exception->errors())->flatten()->first()
                        ?: 'Cannot restore due to an existing SK Official assignment conflict.');
                    throw ValidationException::withMessages([
                        'account' => 'Cannot restore: '.$message,
                    ]);
                }
            }

            $user->restore();
            $user->forceFill([
                'status' => User::STATUS_ACTIVE,
                'tenant_id' => $locked->tenant_id,
                'barangay_id' => $locked->barangay_id ?: $user->barangay_id,
                'role' => $role,
                'name' => $locked->full_name ?: $user->name,
                'email' => $locked->email ?: $user->email,
            ])->save();

            $profile = OfficialProfile::query()->firstOrNew(['user_id' => $user->id]);
            $profile->fill([
                'tenant_id' => $locked->tenant_id,
                'first_name' => $locked->first_name,
                'middle_name' => $locked->middle_name,
                'last_name' => $locked->last_name,
                'suffix' => $locked->suffix,
                'contact_number' => $locked->contact_number,
                'position' => $locked->position,
                'federation_position' => $locked->federation_position,
                'sex' => $locked->sex,
                'date_of_birth' => $locked->date_of_birth,
                'age' => $locked->age,
                'municipality' => $locked->municipality,
                'province' => $locked->province,
                'region' => $locked->region,
            ])->save();

            if ($locked->term_start && $locked->term_end) {
                OfficialTerm::query()->updateOrCreate(
                    [
                        'official_profile_id' => $profile->id,
                        'term_start' => $locked->term_start,
                        'term_end' => $locked->term_end,
                    ],
                    [
                        'status' => OfficialTerm::STATUS_ACTIVE,
                    ]
                );
            }

            $locked->forceFill([
                'restored_at' => now(),
            ])->save();

            $this->auditLog->log('account_restored', $admin, [
                'action' => 'restore_deleted_sk_official',
                'target_user_id' => $user->id,
                'target_name' => $user->name,
                'barangay_id' => $user->barangay_id,
                'archive_id' => $locked->id,
            ]);

            return $user->fresh(['officialProfile.terms', 'barangay']);
        });
    }

    /**
     * Snapshot an SK Official into the dedicated archive table.
     */
    public function archiveOfficial(User $target, User $admin, ?string $reason = null): DeletedSkOfficial
    {
        $target->loadMissing(['officialProfile.latestTerm', 'officialProfile.terms', 'barangay']);

        $profile = $target->officialProfile;
        $term = $profile?->latestTerm ?? $profile?->terms()->latest('id')->first();
        $now = now();

        $payload = [
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
            'restored_at' => null,
        ];

        $existing = DeletedSkOfficial::query()
            ->where('original_user_id', (int) $target->id)
            ->whereNull('restored_at')
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            $existing->fill($payload)->save();

            return $existing->fresh();
        }

        return DeletedSkOfficial::query()->create($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDeletedAccount(DeletedSkOfficial $row): array
    {
        $deletedAt = $row->deleted_at ?? $row->archived_at ?? $row->created_at;

        $termStart = $row->term_start;
        $termEnd = $row->term_end;

        if ((! $termStart || ! $termEnd) && $row->original_user_id) {
            try {
                $fallbackTerm = DB::table('official_profiles as op')
                    ->join('official_terms as ot', 'ot.official_profile_id', '=', 'op.id')
                    ->where('op.user_id', $row->original_user_id)
                    ->orderByDesc('ot.id')
                    ->select('ot.term_start', 'ot.term_end', 'ot.status')
                    ->first();

                if ($fallbackTerm) {
                    $termStart = $termStart ?? ($fallbackTerm->term_start ? Carbon::parse($fallbackTerm->term_start) : null);
                    $termEnd = $termEnd ?? ($fallbackTerm->term_end ? Carbon::parse($fallbackTerm->term_end) : null);

                    if (! $row->term_start && $fallbackTerm->term_start) {
                        $row->forceFill([
                            'term_start' => $fallbackTerm->term_start,
                            'term_end' => $fallbackTerm->term_end,
                            'term_status' => $fallbackTerm->status ?? $row->term_status,
                            'term_label' => $this->formatTermLabel($fallbackTerm->term_start, $fallbackTerm->term_end),
                        ])->saveQuietly();
                    }
                }
            } catch (\Throwable) {
                // Ignore fallback lookup errors
            }
        }

        $termStartDisplay = $termStart
            ? ($termStart instanceof Carbon ? $termStart->format('M d, Y') : Carbon::parse($termStart)->format('M d, Y'))
            : '—';
        $termEndDisplay = $termEnd
            ? ($termEnd instanceof Carbon ? $termEnd->format('M d, Y') : Carbon::parse($termEnd)->format('M d, Y'))
            : '—';

        if ($termStartDisplay === '—' && $row->term_label && str_contains($row->term_label, '-')) {
            [$sYear, $eYear] = explode('-', $row->term_label, 2);
            if (trim($sYear) !== '' && trim($eYear) !== '') {
                $termStartDisplay = trim($sYear);
                $termEndDisplay = trim($eYear);
            }
        }

        $termDisplay = '—';
        if ($termStart && $termEnd) {
            $termDisplay = $termStartDisplay.' — '.$termEndDisplay;
        } elseif ($row->term_label) {
            $termDisplay = $row->term_label;
        }

        $daysRemaining = $this->daysRemaining($deletedAt);
        $expiresAt = $this->retentionExpiresAt($deletedAt);

        $composedName = $this->composeName($row);
        $deletedByName = $row->deleted_by_name;
        if (! $deletedByName && $row->deletedByUser) {
            $deletedByName = $row->deletedByUser->name;
        }
        if (! $deletedByName) {
            $deletedByName = $this->resolveDeletedByNameFromAudit($row);
        }

        return [
            'id' => $row->id,
            'archive_id' => $row->id,
            'original_user_id' => $row->original_user_id,
            'fullName' => $composedName,
            'full_name' => $composedName,
            'firstName' => $row->first_name ?? '',
            'middleName' => $row->middle_name ?? '',
            'lastName' => $row->last_name ?? '',
            'suffix' => $row->suffix ?? '',
            'username' => $row->email ? explode('@', (string) $row->email)[0] : '—',
            'position' => $row->position ?? '—',
            'barangay' => $row->barangay_name ?? ($row->barangay?->name ?? '—'),
            'municipality' => $row->municipality ?? 'Santa Cruz',
            'province' => $row->province ?? 'Laguna',
            'region' => $row->region ?? 'IV-A CALABARZON',
            'contactNumber' => $row->contact_number ?? '—',
            'email' => $row->email,
            'sex' => $row->sex ?? '—',
            'dateOfBirth' => $row->date_of_birth?->format('M d, Y') ?? '—',
            'age' => $row->age,
            'emailVerification' => $row->email_verified_at?->format('m/d/Y h:i A') ?? 'Not Verified',
            'termStart' => $termStartDisplay,
            'termEnd' => $termEndDisplay,
            'term' => $termDisplay,
            'termLabel' => $row->term_label ?? '',
            'accountStatus' => $row->account_status ?? '—',
            'termStatus' => $row->term_status ?? '—',
            'deletedDate' => $deletedAt?->format('M d, Y') ?? '—',
            'deletedTime' => $deletedAt?->format('h:i A') ?? '—',
            'deleted_at' => $deletedAt?->toIso8601String(),
            'deleted_by' => $deletedByName ?: '—',
            'archived_at' => $row->archived_at?->toIso8601String(),
            'retentionDays' => self::RETENTION_DAYS,
            'daysRemaining' => $daysRemaining,
            'retentionTier' => $this->retentionTier($daysRemaining),
            'autoDeleteDate' => $expiresAt?->format('M d, Y') ?? '—',
            'auto_delete_at' => $expiresAt?->toIso8601String(),
        ];
    }

    private function composeName(DeletedSkOfficial $row): string
    {
        $parts = array_filter([
            trim((string) $row->last_name),
            trim((string) $row->first_name),
            trim((string) $row->middle_name),
            trim((string) $row->suffix),
        ], fn ($p) => $p !== '');

        if (! empty($parts)) {
            return implode(', ', $parts);
        }

        return (string) ($row->full_name ?: $row->email ?: '—');
    }

    private function resolveDeletedByNameFromAudit(DeletedSkOfficial $row): string
    {
        if (! $row->original_user_id) {
            return '—';
        }

        try {
            $log = DB::table('admin_activity_logs')
                ->where('action', 'account_deactivated')
                ->where('entity_id', (string) $row->original_user_id)
                ->orderByDesc('created_at')
                ->first();

            if ($log && ! empty($log->metadata)) {
                $meta = is_string($log->metadata) ? json_decode($log->metadata, true) : (array) $log->metadata;
                if (! empty($meta['user_name'])) {
                    return (string) $meta['user_name'];
                }
            }

            if ($log && $log->user_id) {
                $actor = User::query()->find($log->user_id);
                if ($actor?->name) {
                    return $actor->name;
                }
            }
        } catch (\Throwable) {
            // Fallback
        }

        return '—';
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
     * @return array<string, mixed>
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

        // Build rich term options with start and end dates for the dropdown
        $termOptions = [];

        // Terms from deleted records
        foreach ($records as $row) {
            if ($row->term_start && $row->term_end) {
                $val = $row->term_start->format('Y-m-d').'|'.$row->term_end->format('Y-m-d');
                $label = $row->term_start->format('M d, Y').' — '.$row->term_end->format('M d, Y');
                if ($row->term_label) {
                    $label = $row->term_label.' ('.$label.')';
                }
                $termOptions[$val] = [
                    'value' => $val,
                    'label' => $label,
                ];
            } elseif ($row->term_label) {
                $val = $row->term_label;
                $termOptions[$val] = [
                    'value' => $val,
                    'label' => $row->term_label,
                ];
            }
        }

        // Also query active official_terms so the dropdown includes all official terms
        $activeTerms = OfficialTerm::query()
            ->whereNotNull('term_start')
            ->whereNotNull('term_end')
            ->orderByDesc('term_start')
            ->get();

        foreach ($activeTerms as $ot) {
            $val = $ot->term_start->format('Y-m-d').'|'.$ot->term_end->format('Y-m-d');
            if (! isset($termOptions[$val])) {
                $yearRange = $ot->term_start->format('Y').'-'.$ot->term_end->format('Y');
                $dateRange = $ot->term_start->format('M d, Y').' — '.$ot->term_end->format('M d, Y');
                $termOptions[$val] = [
                    'value' => $val,
                    'label' => $yearRange.' ('.$dateRange.')',
                ];
            }
        }

        $terms = array_values($termOptions);

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
