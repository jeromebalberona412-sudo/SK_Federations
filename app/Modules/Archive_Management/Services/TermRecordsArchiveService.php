<?php

namespace App\Modules\Archive_Management\Services;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Archive_Management\Models\ArchivedSkFederationRecord;
use App\Modules\Archive_Management\Models\ArchivedSkOfficialRecord;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TermRecordsArchiveService
{
    public function __construct(
        private readonly AuditLogInterface $auditLog,
    ) {
    }

    public function archiveCompletedTerm(OfficialTerm $term, ?User $archivedBy = null): ?Model
    {
        $term->loadMissing(['officialProfile.user.barangay']);

        $profile = $term->officialProfile;
        $user = $profile?->user;

        if ($profile === null || $user === null) {
            return null;
        }

        $payload = $this->buildSnapshotPayload($term, $profile, $user, $archivedBy);
        $modelClass = $user->role === User::ROLE_SK_FED
            ? ArchivedSkFederationRecord::class
            : ArchivedSkOfficialRecord::class;

        $record = $modelClass::query()->updateOrCreate(
            ['official_term_id' => $term->id],
            $payload,
        );

        if ($record !== null && $archivedBy !== null) {
            $entityType = $user->role === User::ROLE_SK_FED ? 'sk_federation_record' : 'sk_official_record';

            $this->auditLog->log('archive.record_archived', $archivedBy, [
                'action' => $user->role === User::ROLE_SK_FED ? 'archive_federation_record' : 'archive_official_record',
                'entity_type' => $entityType,
                'entity_id' => (string) $record->getKey(),
                'module' => 'archive_management',
                'archived_user_id' => $user->id,
                'barangay_id' => $user->barangay_id,
                'barangay_name' => $user->barangay?->name,
            ]);
        }

        return $record;
    }

    /**
     * @return array{data: list<array<string, mixed>>, stats: array{total: int, positions: int, barangays: int}, filters: array<string, list<string>>}
     */
    public function listOfficialRecords(int $tenantId, Request $request): array
    {
        return $this->listRecords(ArchivedSkOfficialRecord::query(), $tenantId, $request, true);
    }

    /**
     * @return array{data: list<array<string, mixed>>, stats: array{total: int, positions: int, barangays: int}, filters: array<string, list<string>>}
     */
    public function listFederationRecords(int $tenantId, Request $request): array
    {
        return $this->listRecords(ArchivedSkFederationRecord::query(), $tenantId, $request, false);
    }

    /**
     * @param  Builder<ArchivedSkOfficialRecord|ArchivedSkFederationRecord>  $query
     * @return array{data: list<array<string, mixed>>, stats: array{total: int, positions: int, barangays: int}, filters: array<string, list<string>>}
     */
    private function listRecords(Builder $query, int $tenantId, Request $request, bool $includeBarangay): array
    {
        $baseQuery = (clone $query)->where('tenant_id', $tenantId)->orderByDesc('term_end');

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

        if ($includeBarangay && ($barangay = trim((string) $request->input('barangay', ''))) !== '') {
            $filtered->whereHas('barangay', fn (Builder $builder) => $builder->where('name', $barangay));
        }

        if ($position = trim((string) $request->input('position', ''))) {
            $filtered->where('position', $position);
        }

        if ($year = $request->input('year')) {
            if ($year !== 'all' && is_numeric($year)) {
                $filtered->whereYear('term_start', (int) $year);
            }
        }

        if ($term = trim((string) $request->input('term', ''))) {
            if (str_contains($term, '|')) {
                [$termStart, $termEnd] = array_pad(explode('|', $term, 2), 2, null);
                if ($termStart && $termEnd) {
                    $filtered->whereDate('term_start', $termStart)
                        ->whereDate('term_end', $termEnd);
                }
            } else {
                [$startYear, $endYear] = array_pad(explode('-', $term), 2, null);
                if ($startYear && $endYear) {
                    $filtered->whereYear('term_start', (int) $startYear)
                        ->whereYear('term_end', (int) $endYear);
                }
            }
        }

        $records = $filtered->with($includeBarangay ? ['barangay'] : [])
            ->get()
            ->unique(fn (Model $record) => $record->official_term_id ?? 'record-'.$record->id)
            ->values();
        $all = $baseQuery->with($includeBarangay ? ['barangay'] : [])
            ->get()
            ->unique(fn (Model $record) => $record->official_term_id ?? 'record-'.$record->id)
            ->values();

        return [
            'data' => $records->map(fn (Model $record) => $this->formatRecord($record, $includeBarangay))->values()->all(),
            'stats' => [
                'total' => $all->count(),
                'positions' => $all->pluck('position')->unique()->count(),
                'barangays' => $includeBarangay
                    ? $all->pluck('barangay_id')->filter()->unique()->count()
                    : 0,
            ],
            'filters' => [
                'positions' => $all->pluck('position')->filter()->unique()->sort()->values()->all(),
                'barangays' => $includeBarangay
                    ? $all->map(fn (Model $record) => $record->barangay?->name)->filter()->unique()->sort()->values()->all()
                    : [],
                'years' => $all->flatMap(fn (Model $record) => [
                    $record->term_start->format('Y'),
                    $record->term_end->format('Y'),
                ])->unique()->sort()->values()->all(),
                'terms' => $all->map(fn (Model $record) => [
                    'value' => $record->term_start->format('Y-m-d').'|'.$record->term_end->format('Y-m-d'),
                    'label' => $record->term_start->format('F j, Y').' to '.$record->term_end->format('F j, Y'),
                ])->unique('value')->sortBy('value')->values()->all(),
            ],
        ];
    }

    private function termAlreadyArchived(OfficialTerm $term, User $user): bool
    {
        $model = $user->role === User::ROLE_SK_FED
            ? ArchivedSkFederationRecord::class
            : ArchivedSkOfficialRecord::class;

        return $model::query()
            ->where('official_term_id', $term->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshotPayload(OfficialTerm $term, OfficialProfile $profile, User $user, ?User $archivedBy): array
    {
        return [
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
            'term_status' => $term->status,
            'archived_at' => now(),
            'archived_by' => $archivedBy?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRecord(Model $record, bool $includeBarangay): array
    {
        return [
            'id' => $record->id,
            'firstName' => $record->first_name,
            'middleName' => $record->middle_name,
            'lastName' => $record->last_name,
            'suffix' => $record->suffix,
            'position' => $record->position,
            'barangay' => $includeBarangay ? ($record->barangay?->name ?? '') : '',
            'municipality' => $record->municipality,
            'province' => $record->province,
            'region' => $record->region,
            'contactNumber' => $record->contact_number,
            'email' => $record->email,
            'dateOfBirth' => $record->date_of_birth?->format('M j, Y'),
            'age' => $record->age,
            'termStart' => $record->term_start->format('M j, Y'),
            'termEnd' => $record->term_end->format('M j, Y'),
            'termStatus' => 'Completed Term',
            'archivedAt' => $record->archived_at?->toIso8601String(),
        ];
    }
}
