<?php

namespace App\Modules\Archive_Management\Services;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Archive_Management\Models\ArchivedSkFederationRecord;
use App\Modules\Archive_Management\Models\ArchivedSkOfficialRecord;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchivedRecordRestoreService
{
    public function __construct(
        private readonly AuditLogInterface $auditLog,
    ) {
    }

    public function restoreOfficialRecord(int $recordId, User $admin): User
    {
        $record = ArchivedSkOfficialRecord::query()
            ->where('tenant_id', $admin->tenant_id)
            ->findOrFail($recordId);

        return $this->restoreRecord($record, User::ROLE_SK_OFFICIAL, $admin);
    }

    public function restoreFederationRecord(int $recordId, User $admin): User
    {
        $record = ArchivedSkFederationRecord::query()
            ->where('tenant_id', $admin->tenant_id)
            ->findOrFail($recordId);

        return $this->restoreRecord($record, User::ROLE_SK_FED, $admin);
    }

    private function restoreRecord(Model $record, string $role, User $admin): User
    {
        return DB::transaction(function () use ($record, $role, $admin) {
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
                'role' => $role,
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
                'action' => $role === User::ROLE_SK_FED ? 'restore_federation_record' : 'restore_official_record',
                'entity_type' => $role === User::ROLE_SK_FED ? 'sk_federation_record' : 'sk_official_record',
                'entity_id' => (string) $record->getKey(),
                'module' => 'archive_management',
                'restored_user_id' => $user->id,
            ]);

            $record->delete();

            return $user->fresh(['officialProfile.terms', 'barangay']);
        });
    }
}
