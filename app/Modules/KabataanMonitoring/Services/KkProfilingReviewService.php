<?php

namespace App\Modules\KabataanMonitoring\Services;

use App\Models\KabataanRegistration;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\KabataanMonitoring\Support\KkProfilingReviewStatus;
use App\Modules\Shared\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class KkProfilingReviewService
{
    public function __construct(
        private readonly KabataanMonitoringService $monitoring,
        private readonly AuditLogInterface $auditLog,
    ) {}

    /**
     * @return LengthAwarePaginator<int, KabataanRegistration>
     */
    public function submissions(User $user, ?int $barangayId, string $status): LengthAwarePaginator
    {
        $this->assertFederationAccess($user);

        $query = KabataanRegistration::query()
            ->with('barangay:id,name')
            ->where('tenant_id', (int) $user->tenant_id)
            ->whereNull('deleted_at')
            ->when($barangayId !== null, fn ($records) => $records->where('barangay_id', $barangayId));

        KkProfilingReviewStatus::applyFilter($query, $status);

        return $query
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(function (KabataanRegistration $record): KabataanRegistration {
                $record->setAttribute('review_status_label', KkProfilingReviewStatus::labelFor($record));

                return $record;
            });
    }

    public function findSubmission(User $user, int $id, ?int $barangayId = null): KabataanRegistration
    {
        $this->assertFederationAccess($user);

        return KabataanRegistration::query()
            ->with('barangay:id,name')
            ->where('tenant_id', (int) $user->tenant_id)
            ->whereNull('deleted_at')
            ->when($barangayId !== null, fn ($records) => $records->where('barangay_id', $barangayId))
            ->findOrFail($id);
    }

    public function approve(User $reviewer, int $id, ?int $barangayId = null): KabataanRegistration
    {
        return $this->review($reviewer, $id, $barangayId, 'approve', null, null);
    }

    public function reject(
        User $reviewer,
        int $id,
        string $reason,
        ?string $remarks = null,
        ?int $barangayId = null,
    ): KabataanRegistration {
        $reason = trim($reason);
        $remarks = trim((string) $remarks);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => ['A rejection reason is required.'],
            ]);
        }

        return $this->review($reviewer, $id, $barangayId, 'reject', $reason, $remarks);
    }

    private function review(
        User $reviewer,
        int $id,
        ?int $barangayId,
        string $action,
        ?string $reason,
        ?string $remarks,
    ): KabataanRegistration {
        $this->assertFederationAccess($reviewer);

        $record = DB::transaction(function () use ($reviewer, $id, $barangayId, $action, $reason, $remarks): KabataanRegistration {
            $registration = KabataanRegistration::query()
                ->with('barangay:id,name')
                ->where('tenant_id', (int) $reviewer->tenant_id)
                ->whereNull('deleted_at')
                ->when($barangayId !== null, fn ($records) => $records->where('barangay_id', $barangayId))
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! KkProfilingReviewStatus::isPending($registration)) {
                throw ValidationException::withMessages([
                    'submission' => ['This submission is no longer pending review. Refresh the list before trying again.'],
                ]);
            }

            $previousRegistrationStatus = $registration->status;
            $previousEvaluationStatus = $registration->evaluation_status;
            $previousUserStatus = $registration->user_id
                ? DB::table('users')->where('id', $registration->user_id)->value('status')
                : null;
            $previousUserStatus = $previousUserStatus === null ? null : (string) $previousUserStatus;

            if ($action === 'approve') {
                $this->assertAccountCanBeApproved($registration);
                $this->assignRespondentNumber($registration);
                $registration->status = 'active';
                $registration->evaluation_status = 'active';
                $registration->review_notes = null;
                if (Schema::hasColumn('kabataan_registrations', 'rejection_reason')) {
                    $registration->rejection_reason = null;
                }
                if (Schema::hasColumn('kabataan_registrations', 'rejection_remarks')) {
                    $registration->rejection_remarks = null;
                }
            } else {
                $note = $reason;
                if ($remarks !== '') {
                    $note .= ' - '.$remarks;
                }

                $registration->status = 'rejected';
                $registration->review_notes = $note;
                if (Schema::hasColumn('kabataan_registrations', 'rejection_reason')) {
                    $registration->rejection_reason = $reason;
                }
                if (Schema::hasColumn('kabataan_registrations', 'rejection_remarks')) {
                    $registration->rejection_remarks = $remarks !== '' ? $remarks : null;
                }
                $this->archiveRejection(
                    $registration,
                    $reviewer,
                    $remarks,
                    $note,
                    $previousRegistrationStatus,
                    $previousEvaluationStatus,
                    $previousUserStatus,
                );
            }

            $registration->reviewed_by_user_id = $reviewer->id;
            $registration->reviewed_at = now();
            $registration->save();

            if ($action === 'approve' && $registration->user_id && Schema::hasColumn('users', 'status')) {
                DB::table('users')
                    ->where('id', $registration->user_id)
                    ->update(['status' => User::STATUS_ACTIVE]);
            }

            $this->syncSurveyStatus($registration, $action === 'approve' ? 'approved' : 'rejected');

            return $registration->fresh(['barangay:id,name']) ?? $registration;
        }, 3);

        $this->monitoring->forgetListCache($record->barangay?->name, (int) $reviewer->tenant_id);
        $this->auditLog->log('kabataan_registration.reviewed', $reviewer, [
            'action' => $action,
            'entity_type' => 'kabataan_registration',
            'entity_id' => (string) $record->id,
            'module' => 'kabataan_monitoring',
            'barangay_id' => (int) $record->barangay_id,
            'status' => $record->status,
        ]);

        return $record;
    }

    private function assertFederationAccess(User $user): void
    {
        abort_unless($user->canAccessFederationPortal() && (int) $user->tenant_id > 0, 403);
    }

    private function assertAccountCanBeApproved(KabataanRegistration $record): void
    {
        if (! $record->user_id && filled($record->email)) {
            throw ValidationException::withMessages([
                'account' => ['This youth must verify their email and create an account before the KK profiling request can be approved.'],
            ]);
        }

        if ($record->user_id) {
            $account = DB::table('users')->where('id', $record->user_id)->first(['email_verified_at']);
            if ($account === null || (! $account->email_verified_at && ! $record->email_verified_at)) {
                throw ValidationException::withMessages([
                    'account' => ['This youth must verify their email before the KK profiling request can be approved.'],
                ]);
            }
        }
    }

    private function assignRespondentNumber(KabataanRegistration $record): void
    {
        if ($record->respondent_number || ! Schema::hasColumn('kabataan_registrations', 'respondent_number')) {
            return;
        }

        DB::table('barangays')->where('id', $record->barangay_id)->lockForUpdate()->first();

        $sequenceQuery = KabataanRegistration::query()
            ->where('tenant_id', $record->tenant_id)
            ->where('barangay_id', $record->barangay_id)
            ->whereNotNull('respondent_number');
        $sequence = (int) (
            Schema::hasColumn('kabataan_registrations', 'respondent_sequence')
                ? $sequenceQuery->max('respondent_sequence')
                : $sequenceQuery->count()
        ) + 1;

        $record->respondent_number = (string) $sequence;
        if (Schema::hasColumn('kabataan_registrations', 'respondent_sequence')) {
            $record->respondent_sequence = $sequence;
        }

        $formData = is_array($record->form_data) ? $record->form_data : [];
        $formData['respondent_number'] = (string) $sequence;
        $record->form_data = $formData;
    }

    private function syncSurveyStatus(KabataanRegistration $record, string $status): void
    {
        if (! Schema::hasTable('kk_survey_responses')) {
            return;
        }

        if (! Schema::hasColumn('kk_survey_responses', 'status')) {
            return;
        }

        $updates = ['status' => $status];
        if (Schema::hasColumn('kk_survey_responses', 'respondent_number')) {
            $updates['respondent_number'] = $record->respondent_number;
        }

        DB::table('kk_survey_responses')
            ->where('kabataan_registration_id', $record->id)
            ->update($updates);
    }

    private function archiveRejection(
        KabataanRegistration $record,
        User $reviewer,
        ?string $remarks,
        string $note,
        ?string $previousRegistrationStatus,
        ?string $previousEvaluationStatus,
        ?string $previousUserStatus,
    ): void {
        if (! Schema::hasTable('rejected_kk_profiling')) {
            return;
        }

        $columns = Schema::getColumnListing('rejected_kk_profiling');
        $payload = array_intersect_key([
            'tenant_id' => $record->tenant_id,
            'barangay_id' => $record->barangay_id,
            'rejected_by_user_id' => $reviewer->id,
            'rejection_reason' => $note,
            'rejection_remarks' => $remarks !== '' ? $remarks : null,
            'previous_registration_status' => $previousRegistrationStatus,
            'previous_evaluation_status' => $previousEvaluationStatus,
            'previous_user_status' => $previousUserStatus,
            'rejected_at' => now(),
            'restored_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], array_flip($columns));

        $payload['rejection_reason'] = $note;
        if (in_array('rejection_remarks', $columns, true)) {
            $payload['rejection_remarks'] = $remarks !== '' ? $remarks : null;
        }

        DB::table('rejected_kk_profiling')->updateOrInsert(
            ['kabataan_registration_id' => $record->id],
            $payload,
        );
    }
}
