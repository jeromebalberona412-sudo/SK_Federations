<?php

namespace App\Modules\SK_Officials_Records\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Models\User;
use App\Modules\SK_Officials_Records\Models\ArchivedSkOfficialRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SkOfficialsRecordsService
{
    public function __construct(
        private readonly AuditLogInterface $auditLog,
    ) {}

    public function archiveCompletedTerm(OfficialTerm $term, ?User $archivedBy = null): ?ArchivedSkOfficialRecord
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

        if ($user->role === User::ROLE_SK_FED) {
            // Administrators are not archived via term expiry into Records.
            return null;
        }

        $payload = [
            'user_id' => $user->id,
            'official_profile_id' => $profile->id,
            'official_term_id' => $term->id,
            'tenant_id' => $user->tenant_id,
            'barangay_id' => $user->barangay_id,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'middle_name' => $profile->middle_name,
            'suffix' => $profile->suffix,
            'sex' => $profile->sex,
            'date_of_birth' => $profile->date_of_birth,
            'age' => $profile->age,
            'contact_number' => $profile->contact_number,
            'position' => $profile->position,
            'municipality' => $profile->municipality,
            'province' => $profile->province,
            'region' => $profile->region,
            'email' => $user->email,
            'term_start' => $term->term_start,
            'term_end' => $term->term_end,
            'term_status' => $this->normalizeArchivedTermStatus((string) $term->status),
            'archived_by' => $archivedBy?->id,
        ];

        $existing = ArchivedSkOfficialRecord::query()
            ->where('official_term_id', $term->id)
            ->first();

        if ($existing !== null) {
            // Never bump archived_at on refresh/sync — keep the original archive time.
            $payload['archived_at'] = $existing->created_at ?? $existing->archived_at ?? now();
            $existing->fill($payload)->save();
            $record = $existing->fresh(['barangay']);
        } else {
            $payload['archived_at'] = now();
            $record = ArchivedSkOfficialRecord::query()->create($payload);
        }

        if ($record !== null && $archivedBy !== null) {
            $this->auditLog->log('archive.record_archived', $archivedBy, [
                'action' => 'archive_official_record',
                'entity_type' => 'sk_official_record',
                'entity_id' => (string) $record->getKey(),
                'module' => 'sk_officials_records',
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
        $baseQuery = ArchivedSkOfficialRecord::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('term_end')
            ->orderByDesc('id');

        $filtered = clone $baseQuery;

        if ($search = trim((string) $request->input('search', ''))) {
            $filtered->where(function (Builder $builder) use ($search) {
                $builder->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('middle_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('position', 'ilike', "%{$search}%");
            });
        }

        $barangayId = $request->input('barangay_id', $request->input('barangay', ''));
        if ($barangayId !== null && $barangayId !== '' && $barangayId !== 'all') {
            if (is_numeric($barangayId)) {
                $filtered->where('barangay_id', (int) $barangayId);
            } else {
                $filtered->whereHas('barangay', fn (Builder $builder) => $builder->where('name', (string) $barangayId));
            }
        }

        if ($position = trim((string) $request->input('position', ''))) {
            if ($position !== 'all') {
                $filtered->where('position', $position);
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
            ->unique(fn (ArchivedSkOfficialRecord $record) => $record->official_term_id ?? 'record-'.$record->id)
            ->values();

        $all = $baseQuery->with(['barangay'])
            ->get()
            ->unique(fn (ArchivedSkOfficialRecord $record) => $record->official_term_id ?? 'record-'.$record->id)
            ->values();

        return [
            'data' => $records->map(fn (ArchivedSkOfficialRecord $record) => $this->formatRecord($record))->values()->all(),
            'stats' => [
                'total' => $all->count(),
                'positions' => $all->pluck('position')->unique()->count(),
                'barangays' => $all->pluck('barangay_id')->filter()->unique()->count(),
            ],
            'filters' => $this->buildFilterCatalog($tenantId, $all),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ArchivedSkOfficialRecord>  $archiveRows
     * @return array{barangays: list<array{value: string, label: string}>, positions: list<string>, terms: list<array{value: string, label: string}>}
     */
    public function buildFilterCatalog(int $tenantId, $archiveRows = null): array
    {
        $archiveRows ??= ArchivedSkOfficialRecord::query()
            ->where('tenant_id', $tenantId)
            ->with('barangay')
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

        if ($barangays === []) {
            $barangays = $archiveRows
                ->filter(fn (ArchivedSkOfficialRecord $record) => $record->barangay_id && $record->barangay?->name)
                ->map(fn (ArchivedSkOfficialRecord $record) => [
                    'value' => (string) $record->barangay_id,
                    'label' => (string) $record->barangay->name,
                ])
                ->unique('value')
                ->sortBy('label')
                ->values()
                ->all();
        }

        $canonicalPositions = OfficialProfile::OFFICIAL_POSITIONS;
        $extraPositions = $archiveRows->pluck('position')
            ->map(fn ($position) => trim((string) $position))
            ->filter()
            ->reject(fn (string $position) => in_array($position, $canonicalPositions, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
        $positions = array_values(array_merge($canonicalPositions, $extraPositions));

        $terms = $archiveRows
            ->filter(fn (ArchivedSkOfficialRecord $record) => $record->term_start && $record->term_end)
            ->map(fn (ArchivedSkOfficialRecord $record) => [
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

    public function restoreRecord(int $recordId, User $admin): User
    {
        $record = ArchivedSkOfficialRecord::query()
            ->where('tenant_id', $admin->tenant_id)
            ->findOrFail($recordId);

        return DB::transaction(function () use ($record, $admin) {
            $user = User::withTrashed()->find($record->user_id);

            if ($user === null) {
                throw ValidationException::withMessages([
                    'record' => 'The original account for this archived record no longer exists.',
                ]);
            }

            if ($user->trashed()) {
                $user->restore();
            }

            $user->forceFill([
                'status' => User::STATUS_ACTIVE,
                'tenant_id' => $record->tenant_id,
                'barangay_id' => $record->barangay_id,
                'role' => User::ROLE_SK_OFFICIAL,
            ])->save();

            $profile = OfficialProfile::query()->firstOrNew(['user_id' => $user->id]);
            $profile->fill([
                'first_name' => $record->first_name,
                'last_name' => $record->last_name,
                'middle_name' => $record->middle_name,
                'suffix' => $record->suffix,
                'sex' => $record->sex,
                'date_of_birth' => $record->date_of_birth,
                'age' => $record->age,
                'contact_number' => $record->contact_number,
                'position' => $record->position,
                'municipality' => $record->municipality,
                'province' => $record->province,
                'region' => $record->region,
            ])->save();

            $profile->terms()
                ->where('status', OfficialTerm::STATUS_ACTIVE)
                ->update(['status' => OfficialTerm::STATUS_INACTIVE]);

            $termStart = now()->startOfDay();
            $termEnd = $termStart->copy()->addYears(3)->subDay();

            OfficialTerm::query()->create([
                'official_profile_id' => $profile->id,
                'term_start' => $termStart,
                'term_end' => $termEnd,
                'status' => OfficialTerm::STATUS_ACTIVE,
            ]);

            $this->auditLog->log('archive.record_restored', $admin, [
                'action' => 'restore_official_record',
                'entity_type' => 'sk_official_record',
                'entity_id' => (string) $record->getKey(),
                'module' => 'sk_officials_records',
                'restored_user_id' => $user->id,
            ]);

            $record->delete();

            return $user->fresh(['officialProfile.terms', 'barangay']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRecord(ArchivedSkOfficialRecord $record): array
    {
        $termLabel = ($record->term_start && $record->term_end)
            ? $record->term_start->format('Y').'-'.$record->term_end->format('Y')
            : '—';

        return [
            'id' => $record->id,
            'official_term_id' => $record->official_term_id,
            'user_id' => $record->user_id,
            'firstName' => $record->first_name,
            'lastName' => $record->last_name,
            'middleName' => $record->middle_name ?? '',
            'suffix' => $record->suffix ?? '',
            'fullName' => trim("{$record->first_name} {$record->middle_name} {$record->last_name} {$record->suffix}"),
            'position' => $record->position,
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
            'archivedAt' => $this->formatArchivedAt($record),
        ];
    }

    private function formatArchivedAt(ArchivedSkOfficialRecord $record): string
    {
        // Prefer original insert time when sync previously bumped archived_at.
        $archivedAt = $record->archived_at;
        $createdAt = $record->created_at;

        if ($createdAt !== null && ($archivedAt === null || $createdAt->lt($archivedAt))) {
            $archivedAt = $createdAt;
        }

        return $archivedAt?->timezone(config('app.timezone'))->format('F j, Y, g:i a') ?? '—';
    }
}
