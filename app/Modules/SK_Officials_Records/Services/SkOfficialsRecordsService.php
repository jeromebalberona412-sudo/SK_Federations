<?php

namespace App\Modules\SK_Officials_Records\Services;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\SK_Officials_Records\Models\ArchivedSkOfficialRecord;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SkOfficialsRecordsService
{
    public function __construct(
        private readonly AuditLogInterface $auditLog,
    ) {
    }

    public function archiveCompletedTerm(OfficialTerm $term, ?User $archivedBy = null): ?ArchivedSkOfficialRecord
    {
        $term->loadMissing(['officialProfile.user.barangay']);

        $profile = $term->officialProfile;
        $user = $profile?->user;

        if ($profile === null || $user === null) {
            return null;
        }

        if ($user->role === User::ROLE_SK_FED) {
            return app(\App\Modules\SK_Federation_Records\Services\SkFederationRecordsService::class)->archiveCompletedTerm($term, $archivedBy);
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
            'term_status' => $term->status,
            'archived_at' => now(),
            'archived_by' => $archivedBy?->id,
        ];

        $record = ArchivedSkOfficialRecord::query()->updateOrCreate(
            ['official_term_id' => $term->id],
            $payload,
        );

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

        if ($barangay = trim((string) $request->input('barangay', ''))) {
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
            'filters' => [
                'positions' => $all->pluck('position')->filter()->unique()->sort()->values()->all(),
                'barangays' => $all->map(fn (ArchivedSkOfficialRecord $record) => $record->barangay?->name)->filter()->unique()->sort()->values()->all(),
                'years' => $all->flatMap(fn (ArchivedSkOfficialRecord $record) => [
                    $record->term_start?->format('Y'),
                    $record->term_end?->format('Y'),
                ])->filter()->unique()->sort()->values()->all(),
                'terms' => $all->map(fn (ArchivedSkOfficialRecord $record) => [
                    'value' => $record->term_start?->format('Y-m-d').'|'.$record->term_end?->format('Y-m-d'),
                    'label' => $record->term_start?->format('F j, Y').' to '.$record->term_end?->format('F j, Y'),
                ])->unique('value')->sortBy('value')->values()->all(),
            ],
        ];
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
            'term' => $termLabel,
            'status' => $record->term_status,
            'archivedAt' => $record->archived_at?->format('F j, Y, g:i a') ?? '—',
        ];
    }
}
