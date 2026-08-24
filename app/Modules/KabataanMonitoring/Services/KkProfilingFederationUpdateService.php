<?php

namespace App\Modules\KabataanMonitoring\Services;

use App\Models\KabataanRegistration;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\KabataanMonitoring\Notifications\KabataanAccountInviteNotification;
use App\Modules\Shared\Models\User;
use App\Services\BarangayLogoUrlService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class KkProfilingFederationUpdateService
{
    public function __construct(
        private readonly KabataanMonitoringService $monitoring,
        private readonly AuditLogInterface $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{registration: KabataanRegistration, invite_sent: bool, invite_error: ?string}
     */
    public function update(User $user, int $id, array $input): array
    {
        $record = $this->monitoring->find($id);
        if ($record === null) {
            abort(404, 'Kabataan record not found.');
        }

        $previousEmail = strtolower(trim((string) $record->email));
        $newEmail = strtolower(trim((string) ($input['email'] ?? '')));
        $newEmail = $newEmail === '' ? null : $newEmail;

        if ($record->user_id && $newEmail === null) {
            throw ValidationException::withMessages([
                'email' => ['This kabataan already has an account. Email cannot be removed.'],
            ]);
        }

        $this->assertEmailAvailable($newEmail, $record);

        $formData = is_array($record->form_data) ? $record->form_data : [];
        $formData = $this->mergeFormData($formData, $input);
        $formData['email'] = $newEmail;

        $shouldInvite = $newEmail !== null && $newEmail !== $previousEmail;
        $plainToken = null;

        $updated = DB::transaction(function () use ($record, $input, $formData, $newEmail, $previousEmail, $shouldInvite, &$plainToken) {
            if ($shouldInvite) {
                $plainToken = bin2hex(random_bytes(32));
                $formData['account_invite_token_hash'] = hash('sha256', $plainToken);
                $formData['account_invite_expires_at'] = now()->addMinutes($this->inviteExpireMinutes())->toIso8601String();
                unset($formData['account_invite_used_at'], $formData['account_invite_sent_at']);
            }

            $record->update([
                'last_name' => $input['last_name'],
                'first_name' => $input['first_name'],
                'middle_name' => $input['middle_name'] ?? $record->middle_name,
                'suffix' => $input['suffix'] ?? $record->suffix,
                'email' => $newEmail,
                'contact_number' => $input['contact_number'] ?? $record->contact_number,
                'form_data' => $formData,
            ]);

            if ($record->user_id && $newEmail !== null && $newEmail !== $previousEmail) {
                User::query()
                    ->where('id', $record->user_id)
                    ->update([
                        'email' => $newEmail,
                        'email_verified_at' => now(),
                    ]);
            }

            return $record->fresh();
        });

        $inviteSent = false;
        $inviteError = null;
        if (is_string($plainToken) && $plainToken !== '') {
            $inviteError = $this->deliverInviteMail($updated, $plainToken);
            if ($inviteError === null) {
                $inviteSent = true;
                $sentData = is_array($updated->form_data) ? $updated->form_data : [];
                $sentData['account_invite_sent_at'] = now()->toIso8601String();
                $updated->update(['form_data' => $sentData]);
            }
        }

        $this->auditLog->log('kabataan_registration.updated', $user, [
            'action' => 'update',
            'entity_type' => 'kabataan_registration',
            'entity_id' => (string) $id,
            'module' => 'kabataan_monitoring',
            'barangay' => $updated->barangay?->name,
            'name' => $updated->full_name,
            'email_changed' => $shouldInvite,
            'invite_sent' => $inviteSent,
        ]);

        return [
            'registration' => $updated,
            'invite_sent' => $inviteSent,
            'invite_error' => $inviteError,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function editPayload(int $id): ?array
    {
        $record = $this->monitoring->find($id);
        if ($record === null) {
            return null;
        }

        $formData = is_array($record->form_data) ? $record->form_data : [];
        $form = $this->monitoring->buildQuestionnaireFields($record, $formData);
        $fallbackLogo = url('/modules/authentication/images/skoneportal_logo.webp');

        return [
            'id' => $record->id,
            'respondentNumber' => $form['respondent_number'],
            'date' => $form['date'],
            'lastName' => $record->last_name,
            'firstName' => $record->first_name,
            'middleName' => $record->middle_name,
            'suffix' => $record->suffix ?: ($formData['suffix'] ?? ''),
            'customSuffix' => $formData['custom_suffix'] ?? '',
            'region' => $form['region'],
            'province' => $form['province'],
            'city' => $form['city'],
            'barangay' => $form['barangay'],
            'barangayLogoUrl' => $this->logoUrl($record) ?: $fallbackLogo,
            'purokZone' => $this->blankDash($form['purok_zone']),
            'sex' => $this->blankDash($form['sex']),
            'age' => $this->blankDash($form['age']),
            'birthday' => $this->blankDash($form['birthday']),
            'emailAddress' => $record->email ?: $this->blankDash($form['email']),
            'contactNumber' => $record->contact_number ?: $this->blankDash($form['contact_number']),
            'civilStatus' => $this->blankDash($form['civil_status']),
            'youthAgeGroup' => $this->blankDash($form['youth_age_group']),
            'educationalBackground' => $this->blankDash($form['education']),
            'youthClassification' => $this->blankDash($form['youth_classification']),
            'workStatus' => $this->blankDash($form['work_status']),
            'registeredSKVoter' => $this->blankDash($form['sk_voter']),
            'registeredNationalVoter' => $this->blankDash($form['national_voter']),
            'votingHistory' => $this->blankDash($form['sk_voted']),
            'attendedKKAssembly' => $this->blankDash($form['kk_assembly']),
            'kkTimes' => $this->blankDash($form['kk_times']),
            'kkReason' => $this->blankDash($form['kk_reason']),
            'facebookAccount' => $this->blankDash($form['facebook_profile_url'] ?? $form['facebook'] ?? ''),
            'willingToJoinGroupChat' => $this->blankDash($form['group_chat']),
            'signature' => $form['signature'],
            'signatureName' => $this->blankDash($form['signature_name']),
        ];
    }

    private function logoUrl(KabataanRegistration $record): ?string
    {
        return $record->barangay_id
            ? app(BarangayLogoUrlService::class)->resolve((int) $record->barangay_id)
            : null;
    }

    private function blankDash(mixed $value): string
    {
        $text = trim((string) $value);

        return ($text === '' || $text === '—') ? '' : $text;
    }

    private function assertEmailAvailable(?string $email, KabataanRegistration $record): void
    {
        if ($email === null) {
            return;
        }

        $takenByProfile = KabataanRegistration::query()
            ->where('email', $email)
            ->where('id', '!=', $record->id)
            ->whereNull('deleted_at')
            ->when(
                Schema::hasColumn('kabataan_registrations', 'status'),
                fn ($q) => $q->whereNotIn('status', ['rejected'])
            )
            ->exists();

        $takenByUser = User::query()
            ->where('email', $email)
            ->when($record->user_id, fn ($q) => $q->where('id', '!=', $record->user_id))
            ->exists();

        if ($takenByProfile || $takenByUser) {
            throw ValidationException::withMessages([
                'email' => ['This email is already assigned to another account or KK Profiling record.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function mergeFormData(array $existing, array $input): array
    {
        $age = isset($input['age']) ? (int) $input['age'] : (int) ($existing['age'] ?? 0);
        if (! empty($input['birthday'])) {
            $input['birthday'] = $this->normalizeBirthday((string) $input['birthday']);
        }

        $merged = array_merge($existing, array_filter([
            'age' => $input['age'] ?? null,
            'sex' => $input['sex'] ?? null,
            'birthday' => $input['birthday'] ?? null,
            'purok_zone' => $input['purok_zone'] ?? null,
            'civil_status' => $input['civil_status'] ?? null,
            'youth_classification' => $input['youth_classification'] ?? null,
            'youth_age_group' => $this->youthAgeGroupFromAge($age) ?: ($input['youth_age_group'] ?? null),
            'work_status' => $input['work_status'] ?? null,
            'education' => $input['education'] ?? null,
            'sk_voter' => $input['sk_voter'] ?? null,
            'national_voter' => $input['national_voter'] ?? null,
            'sk_voted' => $input['sk_voted'] ?? null,
            'kk_assembly' => $input['kk_assembly'] ?? null,
            'kk_times' => ($input['kk_assembly'] ?? null) === 'Yes' ? ($input['kk_times'] ?? null) : null,
            'kk_reason' => ($input['kk_assembly'] ?? null) === 'No' ? ($input['kk_reason'] ?? null) : null,
            'facebook' => $input['facebook'] ?? $input['facebook_profile_url'] ?? null,
            'facebook_profile_url' => $input['facebook_profile_url'] ?? $input['facebook'] ?? null,
            'last_name' => $input['last_name'] ?? null,
            'first_name' => $input['first_name'] ?? null,
            'middle_name' => $input['middle_name'] ?? null,
            'suffix' => $input['suffix'] ?? null,
            'custom_suffix' => $input['custom_suffix'] ?? null,
            'contact_number' => $input['contact_number'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        $groupChat = trim((string) ($input['group_chat'] ?? ''));
        $merged['group_chat'] = in_array($groupChat, ['Yes', 'No'], true) ? $groupChat : null;

        if (($input['kk_assembly'] ?? null) !== 'Yes') {
            $merged['kk_times'] = null;
        }
        if (($input['kk_assembly'] ?? null) !== 'No') {
            $merged['kk_reason'] = null;
        }

        foreach (['signature', 'signature_image', 'signature_name'] as $protected) {
            if (array_key_exists($protected, $existing)) {
                $merged[$protected] = $existing[$protected];
            } else {
                unset($merged[$protected]);
            }
        }

        return $merged;
    }

    private function deliverInviteMail(KabataanRegistration $registration, string $plainToken): ?string
    {
        $email = strtolower(trim((string) $registration->email));
        if ($email === '') {
            return 'The profile was saved, but there is no email address to send to.';
        }

        $base = rtrim((string) config('services.kabataan_app_url'), '/');
        if ($base === '') {
            return 'The profile was saved, but the Kabataan activation URL is not configured.';
        }

        $activationUrl = $base.'/kkprofiling/account-invite/'.$registration->id.'/'.$plainToken;

        try {
            Notification::route('mail', $email)
                ->notify(new KabataanAccountInviteNotification(
                    (string) $registration->full_name,
                    $activationUrl,
                ));

            return null;
        } catch (\Throwable $e) {
            report($e);
            Log::error('KK Profiling account email failed', [
                'registration_id' => $registration->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return 'The profile was saved, but the activation email could not be sent. Please try saving again.';
        }
    }

    private function normalizeBirthday(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[1], (int) $matches[2]);
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function inviteExpireMinutes(): int
    {
        return max(1, (int) config('services.account_activation_expire_minutes', 1440));
    }

    private function youthAgeGroupFromAge(int $age): string
    {
        if ($age >= 15 && $age <= 17) {
            return 'Child Youth (15-17 yrs old)';
        }
        if ($age >= 18 && $age <= 24) {
            return 'Core Youth (18-24 yrs old)';
        }
        if ($age >= 25 && $age <= 30) {
            return 'Young Adult (25-30 yrs old)';
        }

        return '';
    }
}
