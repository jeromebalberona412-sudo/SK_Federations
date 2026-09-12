<?php

namespace App\Modules\SK_Federation_Records\Services;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\SK_Federation_Records\Models\ArchivedSkFederationRecord;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SkFederationRecordsService
{
    public function __construct(
        private readonly AuditLogInterface $auditLog,
    ) {
    }

    public function archiveCompletedTerm(OfficialTerm $term, ?User $archivedBy = null): ?ArchivedSkFederationRecord
    {
        $term->loadMissing(['officialProfile.user.barangay']);

        $profile = $term->officialProfile;
        $user = $profile?->user;

        if ($profile === null || $user === null) {
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

        /** @var ArchivedSkFederationRecord $record */
        $record = ArchivedSkFederationRecord::query()->updateOrCreate(
            ['official_term_id' => $term->id],
            $payload,
        );

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
     * @return array{data: list<array<string, mixed>>, stats: array{total: int, positions: int, barangays: int}, filters: array<string, list<string>>}
     */
    public function listRecords(int $tenantId, Request $request): array
    {
        $baseQuery = ArchivedSkFederationRecord::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('term_end');

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

        $records = $filtered->get()
            ->unique(fn (ArchivedSkFederationRecord $record) => $record->official_term_id ?? 'record-'.$record->id)
            ->values();
        $all = $baseQuery->get()
            ->unique(fn (ArchivedSkFederationRecord $record) => $record->official_term_id ?? 'record-'.$record->id)
            ->values();

        return [
            'data' => $records->map(fn (ArchivedSkFederationRecord $record) => $this->formatRecord($record))->values()->all(),
            'stats' => [
                'total' => $all->count(),
                'positions' => $all->pluck('position')->unique()->count(),
                'barangays' => 0,
            ],
            'filters' => [
                'positions' => $all->pluck('position')->filter()->unique()->sort()->values()->all(),
                'barangays' => [],
                'years' => $all->flatMap(fn (ArchivedSkFederationRecord $record) => [
                    $record->term_start->format('Y'),
                    $record->term_end->format('Y'),
                ])->unique()->sort()->values()->all(),
                'terms' => $all->map(fn (ArchivedSkFederationRecord $record) => [
                    'value' => $record->term_start->format('Y-m-d').'|'.$record->term_end->format('Y-m-d'),
                    'label' => $record->term_start->format('F j, Y').' to '.$record->term_end->format('F j, Y'),
                ])->unique('value')->sortBy('value')->values()->all(),
            ],
        ];
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
        return [
            'id' => $record->id,
            'firstName' => $record->first_name,
            'middleName' => $record->middle_name,
            'lastName' => $record->last_name,
            'suffix' => $record->suffix,
            'sex' => $record->sex,
            'position' => $record->position,
            'barangay' => '',
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
