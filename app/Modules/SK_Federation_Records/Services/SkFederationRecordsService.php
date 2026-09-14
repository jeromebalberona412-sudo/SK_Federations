<?php

namespace App\Modules\SK_Federation_Records\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Models\User;
use App\Modules\SK_Federation_Records\Models\ArchivedSkFederationRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SkFederationRecordsService
{
    public function __construct(
        private readonly AuditLogInterface $auditLog,
    ) {}

    public function archiveCompletedTerm(OfficialTerm $term, ?User $archivedBy = null): ?ArchivedSkFederationRecord
    {
        $term->loadMissing(['officialProfile']);

        $profile = $term->officialProfile;
        if ($profile === null) {
            return null;
        }

        $user = User::withTrashed()->with('barangay')->find($profile->user_id);

        if ($user === null) {
            return null;
        }

        // Portal administrators are not part of the elected term archive.
        if ($user->role === User::ROLE_SK_FED || $user->isSkFed()) {
            return null;
        }

        $federationRoles = OfficialProfile::FEDERATION_POSITIONS;
        $fedPosition = trim((string) ($profile->federation_position ?? ''));
        $barangayPosition = trim((string) ($profile->position ?? ''));
        $isFederationOfficer = ($fedPosition !== '' && in_array($fedPosition, $federationRoles, true))
            || ($barangayPosition !== '' && in_array($barangayPosition, $federationRoles, true));

        if (! $isFederationOfficer) {
            return null;
        }

        $payload = [
            'user_id' => $user->id,
            'official_profile_id' => $profile->id,
            'official_term_id' => $term->id,
            'tenant_id' => $user->tenant_id,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'middle_name' => $profile->middle_name,
            'suffix' => $profile->suffix,
            'sex' => $profile->sex,
            'date_of_birth' => $profile->date_of_birth,
            'age' => $profile->age,
            'contact_number' => $profile->contact_number,
            'position' => trim((string) ($profile->federation_position ?: $profile->position)),
            'municipality' => $profile->municipality,
            'province' => $profile->province,
            'region' => $profile->region,
            'email' => $user->email,
            'term_start' => $term->term_start,
            'term_end' => $term->term_end,
            'term_status' => $this->normalizeArchivedTermStatus((string) $term->status),
            'archived_by' => $archivedBy?->id,
        ];

        if (Schema::hasColumn('archived_sk_federation_records', 'barangay_id')) {
            $payload['barangay_id'] = $user->barangay_id;
        }

        if (Schema::hasColumn('archived_sk_federation_records', 'federation_position')) {
            $payload['federation_position'] = trim((string) ($profile->federation_position ?? '')) ?: null;
        }

        $existing = ArchivedSkFederationRecord::query()
            ->where('official_term_id', $term->id)
            ->first();

        if ($existing !== null) {
            // Historical snapshots stay immutable for term dates / archive time.
            // Extending live ACTIVE official_terms must not wipe Previous Terms rows.
            unset($payload['term_start'], $payload['term_end'], $payload['term_status']);
            $payload['archived_at'] = $existing->created_at ?? $existing->archived_at ?? now();
            $existing->fill($payload)->save();
            /** @var ArchivedSkFederationRecord $record */
            $record = $existing->fresh(['barangay']);
        } else {
            $payload['archived_at'] = now();
            /** @var ArchivedSkFederationRecord $record */
            $record = ArchivedSkFederationRecord::query()->create($payload);
        }

        if ($archivedBy !== null) {
            $this->auditLog->log('archive.record_archived', $archivedBy, [
                'action' => 'archive_federation_record',
                'entity_type' => 'sk_federation_record',
                'entity_id' => (string) $record->getKey(),
                'module' => 'sk_federation_records',
                'archived_user_id' => $user->id,
                'barangay_id' => $user->barangay_id,
                'barangay_name' => $user->barangay?->name,
            ]);
        }

        return $record;
    }

    /**
     * @return array{data: list<array<string, mixed>>, stats: array{total: int, positions: int, barangays: int}, filters: array<string, mixed>}
     */
    public function listRecords(int $tenantId, Request $request): array
    {
        $federationRoles = OfficialProfile::FEDERATION_POSITIONS;
        $baseQuery = ArchivedSkFederationRecord::query()
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $builder) use ($federationRoles) {
                $builder->whereIn('position', $federationRoles);

                if (Schema::hasColumn('archived_sk_federation_records', 'federation_position')) {
                    $builder->orWhereIn('federation_position', $federationRoles);
                }
            })
            ->orderByDesc('term_end')
            ->orderByDesc('id');

        $filtered = clone $baseQuery;

        if ($search = trim((string) $request->input('search', ''))) {
            $hasFedPosition = Schema::hasColumn('archived_sk_federation_records', 'federation_position');
            $filtered->where(function (Builder $builder) use ($search, $hasFedPosition) {
                $builder->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('middle_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('position', 'ilike', "%{$search}%");

                if ($hasFedPosition) {
                    $builder->orWhere('federation_position', 'ilike', "%{$search}%");
                }
            });
        }

        $barangayId = $request->input('barangay_id', $request->input('barangay', ''));
        if ($barangayId !== null && $barangayId !== '' && $barangayId !== 'all') {
            if (Schema::hasColumn('archived_sk_federation_records', 'barangay_id')) {
                if (is_numeric($barangayId)) {
                    $filtered->where('barangay_id', (int) $barangayId);
                } else {
                    $filtered->whereHas('barangay', fn (Builder $builder) => $builder->where('name', (string) $barangayId));
                }
            }
        }

        if ($position = trim((string) $request->input('position', ''))) {
            if ($position !== 'all') {
                $hasFedPosition = Schema::hasColumn('archived_sk_federation_records', 'federation_position');
                $filtered->where(function (Builder $builder) use ($position, $hasFedPosition) {
                    $builder->where('position', $position);
                    if ($hasFedPosition) {
                        $builder->orWhere('federation_position', $position);
                    }
                });
            }
        }

        if ($term = trim((string) $request->input('term', ''))) {
            if ($term !== 'all') {
                if (str_contains($term, '|')) {
                    [$left, $right] = array_pad(explode('|', $term, 2), 2, null);
                    if ($left && $right && preg_match('/^\d{4}-\d{2}-\d{2}$/', $left) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $right)) {
                        $filtered->whereDate('term_start', $left)
                            ->whereDate('term_end', $right);
                    } elseif ($left && $right && is_numeric($left) && is_numeric($right)) {
                        $filtered->whereYear('term_start', (int) $left)
                            ->whereYear('term_end', (int) $right);
                    }
                }
            }
        }

        $records = $filtered->with(['barangay'])
            ->get()
            ->unique(fn (ArchivedSkFederationRecord $record) => $record->official_term_id ?? 'record-'.$record->id)
            ->sortBy([
                fn (ArchivedSkFederationRecord $record) => $this->federationPositionRank(
                    trim((string) ($record->federation_position ?: $record->position))
                ),
                fn (ArchivedSkFederationRecord $record) => mb_strtolower((string) $record->last_name),
                fn (ArchivedSkFederationRecord $record) => mb_strtolower((string) $record->first_name),
            ])
            ->values();
        $all = $baseQuery->with(['barangay'])
            ->get()
            ->unique(fn (ArchivedSkFederationRecord $record) => $record->official_term_id ?? 'record-'.$record->id)
            ->values();

        return [
            'data' => $records->map(fn (ArchivedSkFederationRecord $record) => $this->formatRecord($record))->values()->all(),
            'stats' => [
                'total' => $all->count(),
                'positions' => $all->map(fn (ArchivedSkFederationRecord $record) => $record->federation_position ?: $record->position)->unique()->count(),
                'barangays' => $all->pluck('barangay_id')->filter()->unique()->count(),
            ],
            'filters' => $this->buildFilterCatalog($tenantId, $all),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ArchivedSkFederationRecord>|null  $archiveRows
     * @return array{barangays: list<array{value: string, label: string}>, positions: list<string>, terms: list<array{value: string, label: string}>}
     */
    public function buildFilterCatalog(int $tenantId, $archiveRows = null): array
    {
        $federationRoles = OfficialProfile::FEDERATION_POSITIONS;

        $archiveRows ??= ArchivedSkFederationRecord::query()
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $builder) use ($federationRoles) {
                $builder->whereIn('position', $federationRoles);
                if (Schema::hasColumn('archived_sk_federation_records', 'federation_position')) {
                    $builder->orWhereIn('federation_position', $federationRoles);
                }
            })
            ->with(['barangay'])
            ->get();

        $barangays = Barangay::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Barangay $barangay) => [
                'value' => (string) $barangay->id,
                'label' => (string) $barangay->name,
            ])
            ->values()
            ->all();

        $extraPositions = $archiveRows
            ->map(fn (ArchivedSkFederationRecord $record) => trim((string) ($record->federation_position ?: $record->position)))
            ->filter()
            ->reject(fn (string $position) => in_array($position, $federationRoles, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
        $positions = array_values(array_merge($federationRoles, $extraPositions));

        $terms = $archiveRows
            ->filter(fn (ArchivedSkFederationRecord $record) => $record->term_start && $record->term_end)
            ->map(fn (ArchivedSkFederationRecord $record) => [
                'value' => $record->term_start->format('Y-m-d').'|'.$record->term_end->format('Y-m-d'),
                'label' => $record->term_start->format('Y').'–'.$record->term_end->format('Y'),
            ])
            ->unique('label')
            ->sortByDesc('value')
            ->values()
            ->all();

        return [
            'barangays' => $barangays,
            'positions' => $positions,
            'terms' => $terms,
        ];
    }

    private function normalizeArchivedTermStatus(string $status): string
    {
        $normalized = strtoupper(trim($status));

        if ($normalized === OfficialTerm::STATUS_REPLACED) {
            return OfficialTerm::STATUS_REPLACED;
        }

        return OfficialTerm::STATUS_EXPIRED;
    }

    public function restoreRecord(int $recordId, User $actor): User
    {
        return DB::transaction(function () use ($recordId, $actor) {
            /** @var ArchivedSkFederationRecord|null $record */
            $record = ArchivedSkFederationRecord::query()->lockForUpdate()->find($recordId);

            if ($record === null) {
                throw new RuntimeException('Archived federation record not found.');
            }

            $user = null;
            if ($record->user_id !== null) {
                $user = User::withTrashed()->find($record->user_id);
            }

            if ($user === null && $record->email) {
                $user = User::withTrashed()->where('email', $record->email)->first();
            }

            if ($user === null) {
                throw new RuntimeException('Associated user account could not be found to restore.');
            }

            if ($user->trashed()) {
                $user->restore();
            }

            $user->status = User::STATUS_ACTIVE;
            $user->save();

            $profile = OfficialProfile::query()->where('user_id', $user->id)->first();
            if ($profile !== null) {
                OfficialTerm::query()
                    ->where('official_profile_id', $profile->id)
                    ->update(['status' => OfficialTerm::STATUS_INACTIVE]);

                if ($record->official_term_id) {
                    OfficialTerm::query()
                        ->where('id', $record->official_term_id)
                        ->update(['status' => OfficialTerm::STATUS_ACTIVE]);
                }
            }

            $this->auditLog->log('archive.record_restored', $actor, [
                'action' => 'restore_federation_record',
                'entity_type' => 'sk_federation_record',
                'entity_id' => (string) $record->id,
                'module' => 'sk_federation_records',
                'restored_user_id' => $user->id,
            ]);

            return $user;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRecord(ArchivedSkFederationRecord $record): array
    {
        $federationPosition = trim((string) ($record->federation_position ?: $record->position));
        $termLabel = ($record->term_start && $record->term_end)
            ? $record->term_start->format('Y').'-'.$record->term_end->format('Y')
            : '—';

        return [
            'id' => $record->id,
            'official_term_id' => $record->official_term_id,
            'user_id' => $record->user_id,
            'firstName' => $record->first_name,
            'middleName' => $record->middle_name ?? '',
            'lastName' => $record->last_name,
            'suffix' => $record->suffix ?? '',
            'fullName' => trim("{$record->first_name} {$record->middle_name} {$record->last_name} {$record->suffix}"),
            'position' => $federationPosition !== '' ? $federationPosition : '—',
            'federationPosition' => $federationPosition !== '' ? $federationPosition : '—',
            'barangay' => $record->barangay?->name ?? '—',
            'barangayId' => $record->barangay_id,
            'municipality' => $record->municipality ?? 'Santa Cruz',
            'province' => $record->province ?? 'Laguna',
            'region' => $record->region ?? 'IV-A CALABARZON',
            'contactNumber' => $record->contact_number ?? '—',
            'email' => $record->email ?? '—',
            'sex' => $record->sex ?? '—',
            'dateOfBirth' => $record->date_of_birth?->format('F j, Y') ?? '—',
            'age' => $record->age ?? '—',
            'termStart' => $record->term_start?->format('F j, Y') ?? '—',
            'termEnd' => $record->term_end?->format('F j, Y') ?? '—',
            'termStartRaw' => $record->term_start?->format('Y-m-d'),
            'termEndRaw' => $record->term_end?->format('Y-m-d'),
            'term' => $termLabel,
            'status' => $this->normalizeArchivedTermStatus((string) ($record->term_status ?? OfficialTerm::STATUS_EXPIRED)),
            'termStatus' => $this->normalizeArchivedTermStatus((string) ($record->term_status ?? OfficialTerm::STATUS_EXPIRED)),
            'archivedAt' => $this->formatArchivedAt($record),
            'positionRank' => $this->federationPositionRank($federationPosition),
        ];
    }

    private function formatArchivedAt(ArchivedSkFederationRecord $record): string
    {
        $archivedAt = $record->archived_at;
        $createdAt = $record->created_at;

        if ($createdAt !== null && ($archivedAt === null || $createdAt->lt($archivedAt))) {
            $archivedAt = $createdAt;
        }

        return $archivedAt?->timezone(config('app.timezone'))->format('F j, Y, g:i a') ?? '—';
    }

    private function federationPositionRank(string $position): int
    {
        $index = array_search($position, OfficialProfile::FEDERATION_POSITIONS, true);

        return $index === false ? 999 : (int) $index;
    }
}
