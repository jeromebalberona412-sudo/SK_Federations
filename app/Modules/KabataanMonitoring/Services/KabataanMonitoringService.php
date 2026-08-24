<?php

namespace App\Modules\KabataanMonitoring\Services;

use App\Models\KabataanRegistration;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Models\User;
use App\Services\BarangayLogoUrlService;
use Illuminate\Support\Facades\Schema;

class KabataanMonitoringService
{
    public function __construct(
        private readonly BarangayLogoUrlService $logoUrls,
        private readonly AuditLogInterface $auditLog,
    ) {
    }
    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(): array
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return [];
        }

        $query = KabataanRegistration::query()
            ->with('barangay:id,name')
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'email_verified', 'password_set']);

        if (Schema::hasColumn('kabataan_registrations', 'evaluation_status')) {
            $query->where(function ($q) {
                $q->whereNull('evaluation_status')
                    ->orWhereIn('evaluation_status', ['active', 'Auto Approved']);
            });
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (KabataanRegistration $record) => $this->formatRecord($record))
            ->all();
    }

    /**
     * @return list<string>
     */
    public function registrationYears(?string $barangayName = null): array
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return [];
        }

        $query = KabataanRegistration::query()
            ->whereNull('deleted_at')
            ->whereNotNull('submitted_at')
            ->whereIn('status', ['active', 'email_verified', 'password_set']);

        if (Schema::hasColumn('kabataan_registrations', 'evaluation_status')) {
            $query->where(function ($q) {
                $q->whereNull('evaluation_status')
                    ->orWhereIn('evaluation_status', ['active', 'Auto Approved']);
            });
        }

        if ($barangayName) {
            $query->whereHas('barangay', fn ($builder) => $builder->where('name', $barangayName));
        }

        return $query
            ->selectRaw('EXTRACT(YEAR FROM submitted_at)::integer as registration_year')
            ->distinct()
            ->orderByDesc('registration_year')
            ->pluck('registration_year')
            ->map(fn ($year) => (string) $year)
            ->values()
            ->all();
    }

    public function find(int $id): ?KabataanRegistration
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return null;
        }

        return KabataanRegistration::query()
            ->with('barangay:id,name')
            ->whereNull('deleted_at')
            ->find($id);
    }

    public function destroy(User $user, int $id): void
    {
        $record = $this->find($id);

        if ($record === null) {
            abort(404, 'Kabataan record not found.');
        }

        $record->delete();

        $this->auditLog->log('kabataan_registration.deleted', $user, [
            'action' => 'delete',
            'entity_type' => 'kabataan_registration',
            'entity_id' => (string) $id,
            'module' => 'kabataan_monitoring',
            'barangay' => $record->barangay?->name,
            'name' => $record->full_name,
        ]);
    }

    public function renderQuestionnaireHtml(int $id): ?string
    {
        $record = $this->find($id);
        if ($record === null) {
            return null;
        }

        $formData = is_array($record->form_data) ? $record->form_data : [];

        return view('kabataan_monitoring::partials.kk-survey-readonly', $this->questionnaireViewData(
            $record,
            $this->buildQuestionnaireFields($record, $formData),
        ))->render();
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public function buildQuestionnaireFields(KabataanRegistration $record, array $formData): array
    {
        return [
            'respondent_number' => $this->displayRespondentNumber($record),
            'date' => $record->submitted_at?->format('m/d/Y') ?? '—',
            'last_name' => $record->last_name,
            'first_name' => $record->first_name,
            'middle_name' => $record->middle_name ?: '—',
            'suffix' => $this->formSuffixValue($record, $formData),
            'region' => $this->formValue($formData, 'region') !== '—' ? $this->formValue($formData, 'region') : 'Region IV-A (CALABARZON)',
            'province' => $this->formValue($formData, 'province') !== '—' ? $this->formValue($formData, 'province') : 'Laguna',
            'city' => $this->formValue($formData, 'city') !== '—' ? $this->formValue($formData, 'city') : ($this->formValue($formData, 'municipality') !== '—' ? $this->formValue($formData, 'municipality') : 'Santa Cruz'),
            'barangay' => $record->barangay?->name ?? $this->formValue($formData, 'barangay'),
            'purok_zone' => $this->formValue($formData, 'purok_zone'),
            'sex' => $this->formValue($formData, 'sex'),
            'age' => $this->formValue($formData, 'age'),
            'birthday' => $this->formValue($formData, 'birthday'),
            'email' => $record->email ?: $this->formValue($formData, 'email'),
            'contact_number' => $record->contact_number ?: $this->formValue($formData, 'contact_number'),
            'civil_status' => $this->formValue($formData, 'civil_status'),
            'youth_age_group' => $this->formValue($formData, 'youth_age_group'),
            'education' => $this->formValue($formData, 'education'),
            'youth_classification' => $this->formValue($formData, 'youth_classification'),
            'work_status' => $this->formValue($formData, 'work_status'),
            'sk_voter' => $this->formValue($formData, 'sk_voter'),
            'national_voter' => $this->formValue($formData, 'national_voter'),
            'sk_voted' => $this->formValue($formData, 'sk_voted'),
            'kk_times' => $this->formValue($formData, 'kk_times'),
            'kk_assembly' => $this->formValue($formData, 'kk_assembly'),
            'kk_reason' => $this->formValue($formData, 'kk_reason'),
            'facebook' => $this->formValue($formData, 'facebook_profile_url') !== '—'
                ? $this->formValue($formData, 'facebook_profile_url')
                : $this->formValue($formData, 'facebook'),
            'facebook_profile_url' => $this->formValue($formData, 'facebook_profile_url') !== '—'
                ? $this->formValue($formData, 'facebook_profile_url')
                : $this->formValue($formData, 'facebook'),
            'group_chat' => $this->formValue($formData, 'group_chat'),
            'signature_name' => $this->resolveSignatureName($record, $formData),
            'signature' => $this->resolveSignature($formData),
        ];
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function resolveSignature(array $formData): ?string
    {
        foreach (['signature', 'signature_image'] as $key) {
            $value = $formData[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value === '' || $value === '—') {
                continue;
            }

            if (str_starts_with($value, 'data:image') || str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function resolveSignatureName(KabataanRegistration $record, array $formData): string
    {
        $name = trim($this->formValue($formData, 'signature_name'));
        if ($name !== '—') {
            return $name;
        }

        $fullName = trim($record->first_name.' '.($record->middle_name ? $record->middle_name.' ' : '').$record->last_name);
        $suffix = trim((string) ($record->suffix ?? ''));
        if ($suffix !== '') {
            $fullName .= ', '.$suffix;
        }

        return $fullName !== '' ? $fullName : '—';
    }

    /**
     * @param  array<string, mixed>  $form
     */
    public function questionnaireViewData(KabataanRegistration $record, array $form): array
    {
        $fallbackLogo = url('/modules/authentication/images/skoneportal_logo.webp');
        $barangayLogoUrl = $this->logoUrls->resolve($record->barangay_id) ?: $fallbackLogo;

        return [
            'record' => $record,
            'form' => $form,
            'barangayLogoUrl' => $barangayLogoUrl,
            'barangayName' => $record->barangay?->name ?? $form['barangay'] ?? 'Barangay',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRecord(KabataanRegistration $record): array
    {
        $formData = is_array($record->form_data) ? $record->form_data : [];

        return [
            'id' => $record->id,
            'slug' => (string) $record->id,
            'respondentNumber' => $this->displayRespondentNumber($record),
            'barangayLogoUrl' => $record->barangay_id ? ($this->logoUrls->resolve((int) $record->barangay_id) ?: null) : null,
            'firstName' => $record->first_name,
            'middleName' => $record->middle_name,
            'lastName' => $record->last_name,
            'suffix' => $this->normalizeSuffix($record->suffix),
            'name' => $this->formatDisplayName($record),
            'barangay' => $record->barangay?->name ?? '—',
            'age' => $this->formValue($formData, 'age'),
            'sex' => $this->formValue($formData, 'sex'),
            'purokZone' => $this->formValue($formData, 'purok_zone'),
            'registeredVoter' => $this->formValue($formData, 'sk_voter'),
            'votedLastElection' => $this->formValue($formData, 'sk_voted'),
            'kkAssembly' => $this->formValue($formData, 'kk_assembly'),
            'kkTimes' => $this->formValue($formData, 'kk_times'),
            'youthAgeGroup' => $this->formValue($formData, 'youth_age_group'),
            'region' => $this->formValue($formData, 'region') !== '—' ? $this->formValue($formData, 'region') : 'IV-A',
            'province' => $this->formValue($formData, 'province') !== '—' ? $this->formValue($formData, 'province') : 'Laguna',
            'city' => $this->formValue($formData, 'city') !== '—'
                ? $this->formValue($formData, 'city')
                : ($this->formValue($formData, 'municipality') !== '—' ? $this->formValue($formData, 'municipality') : 'Santa Cruz'),
            'birthday' => $this->formValue($formData, 'birthday'),
            'email' => $record->email ?: $this->formValue($formData, 'email'),
            'contactNumber' => $record->contact_number ?: $this->formValue($formData, 'contact_number'),
            'focus' => $this->formValue($formData, 'youth_classification'),
            'youthClassification' => $this->formValue($formData, 'youth_classification'),
            'civilStatus' => $this->formValue($formData, 'civil_status'),
            'education' => $this->formValue($formData, 'education'),
            'workStatus' => $this->formValue($formData, 'work_status'),
            'status' => $this->resolveParticipationStatus($formData),
            'attendance' => $this->formValue($formData, 'kk_assembly'),
            'lastCheckIn' => $record->submitted_at?->format('M j, Y') ?? '—',
            'submittedAt' => $record->submitted_at?->toIso8601String(),
            'submittedDate' => $record->submitted_at?->toDateString(),
            'submittedYear' => $record->submitted_at ? (int) $record->submitted_at->format('Y') : null,
            'score' => '—',
            'programs' => [],
            'recommendations' => [],
            'timeline' => [],
        ];
    }

    private function displayRespondentNumber(KabataanRegistration $record): string
    {
        if ($record->respondent_sequence) {
            return str_pad((string) $record->respondent_sequence, 2, '0', STR_PAD_LEFT);
        }

        if ($record->respondent_number) {
            $fullNumber = (string) $record->respondent_number;
            $last = strrpos($fullNumber, '-') !== false
                ? substr($fullNumber, strrpos($fullNumber, '-') + 1)
                : $fullNumber;

            if ($last === '') {
                return '—';
            }

            return str_pad((string) ((int) $last), 2, '0', STR_PAD_LEFT);
        }

        return '—';
    }

    private function formatDisplayName(KabataanRegistration $record): string
    {
        $nameParts = array_values(array_filter([
            trim((string) ($record->first_name ?? '')),
            trim((string) ($record->middle_name ?? '')),
        ], fn (string $part) => $part !== ''));

        $firstMiddle = $nameParts !== [] ? implode(',', $nameParts) : '';
        $last = trim((string) ($record->last_name ?? ''));
        $suffix = $this->normalizeSuffix($record->suffix);
        $suffixPart = $suffix !== '' ? ','.$suffix : '';

        if ($last !== '' && $firstMiddle !== '') {
            return $last.','.$firstMiddle.$suffixPart;
        }

        if ($last !== '') {
            return $last.$suffixPart;
        }

        if ($firstMiddle !== '') {
            return $firstMiddle.$suffixPart;
        }

        return '—';
    }

    private function formSuffixValue(KabataanRegistration $record, array $formData): string
    {
        $suffix = trim((string) ($record->suffix ?? ''));
        if ($suffix !== '') {
            return $suffix;
        }

        return $this->formValue($formData, 'suffix');
    }

    private function normalizeSuffix(?string $suffix): string
    {
        $suffix = trim((string) ($suffix ?? ''));

        if ($suffix === '' || strcasecmp($suffix, 'none') === 0) {
            return '';
        }

        return $suffix;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function formValue(array $formData, string $key): string
    {
        $value = $formData[$key] ?? '—';
        if (is_array($value)) {
            $value = $value[0] ?? '—';
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : '—';
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function resolveParticipationStatus(array $formData): string
    {
        $kk = strtolower($this->formValue($formData, 'kk_assembly'));

        if (in_array($kk, ['yes', 'attended', '1', 'true'], true)) {
            return 'active';
        }

        if (in_array($kk, ['sometimes', 'moderate', 'partial'], true)) {
            return 'moderate';
        }

        return 'inactive';
    }
}
