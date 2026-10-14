<?php

namespace App\Modules\KabataanMonitoring\Services;

use App\Models\KabataanRegistration;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\KabataanMonitoring\Support\KabataanQuestionnairePrintPresenter;
use App\Modules\KabataanMonitoring\Support\KkProfilingReviewStatus;
use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Models\User;
use App\Services\BarangayLogoUrlService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KabataanMonitoringService
{
    private const LIST_CACHE_TTL = 300;

    public function __construct(
        private readonly BarangayLogoUrlService $logoUrls,
        private readonly AuditLogInterface $auditLog,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(
        ?string $barangayName = null,
        ?int $tenantId = null,
        bool $includeAllReviewStatuses = false,
    ): array {
        $barangayName = $barangayName !== null ? trim($barangayName) : '';
        if (! $tenantId) {
            return [];
        }
        $cacheKey = $this->listCacheKey($barangayName, $tenantId, $includeAllReviewStatuses);

        return $this->monitoringCache()->remember($cacheKey, self::LIST_CACHE_TTL, function () use ($barangayName, $tenantId, $includeAllReviewStatuses) {
            return $this->listAllUncached(
                $barangayName !== '' ? $barangayName : null,
                $tenantId,
                $includeAllReviewStatuses,
            );
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listAllUncached(
        ?string $barangayName,
        int $tenantId,
        bool $includeAllReviewStatuses = false,
    ): array {
        if (! Schema::hasTable('kabataan_registrations')) {
            return [];
        }

        $query = KabataanRegistration::query()
            ->with('barangay:id,name')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if (! $includeAllReviewStatuses) {
            $query->where(function ($records) {
                $records->where(function ($approved) {
                    $approved->whereIn('status', ['active', 'email_verified', 'password_set']);
                    if (Schema::hasColumn('kabataan_registrations', 'evaluation_status')) {
                        $approved->where(function ($evaluation) {
                            $evaluation->whereNull('evaluation_status')
                                ->orWhereIn('evaluation_status', ['active', 'Auto Approved']);
                        });
                    }
                })->orWhere(function ($monitoring) {
                    $monitoring->where('status', 'pending_verification')
                        ->whereJsonContains('form_data->entry_source', 'federation_monitoring');
                });
            });
        }

        $barangayName = $barangayName !== null ? trim($barangayName) : '';
        if ($barangayName !== '') {
            $query->whereHas('barangay', fn ($builder) => $builder->where('name', $barangayName));
        }

        $records = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Resolve each barangay logo once (not once per youth record).
        $logoMap = [];
        $barangayIds = $records
            ->pluck('barangay_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $logoMap = $barangayIds === [] ? [] : $this->logoUrls->resolveMany($barangayIds);

        return $records
            ->map(fn (KabataanRegistration $record) => $this->formatRecord(
                $record,
                $logoMap[(int) ($record->barangay_id ?? 0)] ?? null
            ))
            ->all();
    }

    public function forgetListCache(?string $barangayName = null, ?int $tenantId = null): void
    {
        $barangayName = $barangayName !== null ? trim($barangayName) : '';
        if (! $tenantId) {
            return;
        }
        $cache = $this->monitoringCache();
        $cache->forget($this->listCacheKey('', $tenantId));
        $cache->forget($this->listCacheKey('', $tenantId, true));
        $cache->forget($this->yearsCacheKey('', $tenantId));

        if ($barangayName !== '') {
            $cache->forget($this->listCacheKey($barangayName, $tenantId));
            $cache->forget($this->listCacheKey($barangayName, $tenantId, true));
            $cache->forget($this->yearsCacheKey($barangayName, $tenantId));
        }
    }

    private function monitoringCache(): Repository
    {
        // The default store is remote Postgres. These keys already include the
        // tenant and barangay, and forgetListCache clears the same store.
        return Cache::store('file');
    }

    private function listCacheKey(string $barangayName, int $tenantId, bool $includeAllReviewStatuses = false): string
    {
        $scope = $includeAllReviewStatuses ? 'review' : 'approved';

        return 'skfed:km:list:v4:'.$scope.':'.$tenantId.':'.md5(strtolower($barangayName !== '' ? $barangayName : 'all'));
    }

    private function yearsCacheKey(string $barangayName, int $tenantId): string
    {
        return 'skfed:km:years:v3:'.$tenantId.':'.md5(strtolower($barangayName !== '' ? $barangayName : 'all'));
    }

    /**
     * @return list<string>
     */
    public function registrationYears(?string $barangayName = null, ?int $tenantId = null): array
    {
        $barangayName = $barangayName !== null ? trim($barangayName) : '';
        if (! $tenantId) {
            return [];
        }
        $cacheKey = $this->yearsCacheKey($barangayName, $tenantId);

        return $this->monitoringCache()->remember($cacheKey, self::LIST_CACHE_TTL, function () use ($barangayName, $tenantId) {
            return $this->registrationYearsUncached($barangayName !== '' ? $barangayName : null, $tenantId);
        });
    }

    /**
     * @return list<string>
     */
    private function registrationYearsUncached(?string $barangayName, int $tenantId): array
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return [];
        }

        $query = KabataanRegistration::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('submitted_at')
            ->where(function ($records) {
                $records->where(function ($approved) {
                    $approved->whereIn('status', ['active', 'email_verified', 'password_set']);
                    if (Schema::hasColumn('kabataan_registrations', 'evaluation_status')) {
                        $approved->where(function ($evaluation) {
                            $evaluation->whereNull('evaluation_status')
                                ->orWhereIn('evaluation_status', ['active', 'Auto Approved']);
                        });
                    }
                })->orWhere(function ($monitoring) {
                    $monitoring->where('status', 'pending_verification')
                        ->whereJsonContains('form_data->entry_source', 'federation_monitoring');
                });
            });

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

    public function find(int $id, ?int $tenantId = null): ?KabataanRegistration
    {
        if (! Schema::hasTable('kabataan_registrations') || ! $tenantId) {
            return null;
        }

        return KabataanRegistration::query()
            ->with('barangay:id,name')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(function ($records) {
                $records->where(function ($approved) {
                    $approved->whereIn('status', ['active', 'email_verified', 'password_set']);
                    if (Schema::hasColumn('kabataan_registrations', 'evaluation_status')) {
                        $approved->where(function ($evaluation) {
                            $evaluation->whereNull('evaluation_status')
                                ->orWhereIn('evaluation_status', ['active', 'Auto Approved']);
                        });
                    }
                })->orWhere(function ($monitoring) {
                    $monitoring->where('status', 'pending_verification')
                        ->whereJsonContains('form_data->entry_source', 'federation_monitoring');
                });
            })
            ->find($id);
    }

    public function findSubmissionForReview(
        int $id,
        int $tenantId,
        ?int $barangayId = null,
    ): ?KabataanRegistration {
        if (! Schema::hasTable('kabataan_registrations') || $tenantId <= 0) {
            return null;
        }

        return KabataanRegistration::query()
            ->with('barangay:id,name')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($barangayId !== null, fn ($query) => $query->where('barangay_id', $barangayId))
            ->find($id);
    }

    public function destroy(User $user, int $id): void
    {
        $record = $this->find($id, (int) $user->tenant_id);

        if ($record === null) {
            abort(404, 'Kabataan record not found.');
        }

        $record->delete();

        $this->forgetListCache($record->barangay?->name, (int) $user->tenant_id);

        $this->auditLog->log('kabataan_registration.deleted', $user, [
            'action' => 'delete',
            'entity_type' => 'kabataan_registration',
            'entity_id' => (string) $id,
            'module' => 'kabataan_monitoring',
            'barangay' => $record->barangay?->name,
            'name' => $record->full_name,
        ]);
    }

    /**
     * Soft-delete selected registrations within the current Federation barangay.
     *
     * @param  list<int>  $ids
     */
    public function destroySelected(User $user, array $ids, int $barangayId): int
    {
        abort_unless($user->canAccessFederationPortal() && (int) $user->tenant_id > 0, 403);
        abort_unless(
            Barangay::query()
                ->where('tenant_id', (int) $user->tenant_id)
                ->whereKey($barangayId)
                ->exists(),
            404,
        );

        $records = DB::transaction(function () use ($user, $ids, $barangayId) {
            $records = KabataanRegistration::query()
                ->with('barangay:id,name')
                ->where('tenant_id', (int) $user->tenant_id)
                ->where('barangay_id', $barangayId)
                ->whereNull('deleted_at')
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            abort_unless($records->count() === count($ids), 404, 'One or more selected records are unavailable.');

            foreach ($records as $record) {
                $record->delete();
            }

            return $records;
        });

        foreach ($records as $record) {
            $this->auditLog->log('kabataan_registration.deleted', $user, [
                'action' => 'delete',
                'entity_type' => 'kabataan_registration',
                'entity_id' => (string) $record->id,
                'module' => 'kabataan_monitoring',
                'barangay_id' => (int) $barangayId,
                'name' => $record->full_name,
            ]);
        }

        $this->forgetListCache($records->first()?->barangay?->name, (int) $user->tenant_id);

        return $records->count();
    }

    public function renderQuestionnaireHtml(int $id, int $tenantId, bool $forReview = false, ?int $barangayId = null): ?string
    {
        $record = $forReview
            ? $this->findSubmissionForReview($id, $tenantId, $barangayId)
            : $this->find($id, $tenantId);
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
     * @return array<string, mixed>|null
     */
    public function printSheetData(int $id, int $tenantId): ?array
    {
        $record = $this->find($id, $tenantId);
        if ($record === null) {
            return null;
        }

        $formData = is_array($record->form_data) ? $record->form_data : [];
        $form = $this->buildQuestionnaireFields($record, $formData);
        $view = $this->questionnaireViewData($record, $form);

        return KabataanQuestionnairePrintPresenter::fromForm(
            $form,
            $view['barangayLogoUrl'] ?? null,
        );
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function printSheets(array $ids, int $tenantId): array
    {
        $sheets = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $sheet = $this->printSheetData((int) $id, $tenantId);
            if ($sheet !== null) {
                $sheets[] = $sheet;
            }
        }

        return $sheets;
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
        $suffix = $this->normalizeSuffix($record->suffix);
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
            'supportingDocumentsRequired' => (bool) (
                is_array($record->form_data)
                    ? ($record->form_data['supporting_documents_required'] ?? false)
                    : false
            ),
            'barangayLogoUrl' => $barangayLogoUrl,
            'barangayName' => $record->barangay?->name ?? $form['barangay'] ?? 'Barangay',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRecord(KabataanRegistration $record, ?string $barangayLogoUrl = null): array
    {
        $formData = is_array($record->form_data) ? $record->form_data : [];

        return [
            'id' => $record->id,
            'slug' => (string) $record->id,
            'respondentNumber' => $this->displayRespondentNumber($record),
            'barangayLogoUrl' => $barangayLogoUrl,
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
            'reviewStatus' => KkProfilingReviewStatus::labelFor($record),
            'recordSource' => ($formData['entry_source'] ?? null) === 'federation_monitoring'
                ? 'monitoring_upload'
                : 'approved_registry',
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
        if ($suffix === '') {
            $suffix = trim((string) ($this->formValue($formData, 'suffix')));
        }

        if ($suffix === '' || $suffix === '—' || $suffix === '-'
            || strcasecmp($suffix, 'none') === 0
            || strcasecmp($suffix, 'n/a') === 0
            || strcasecmp($suffix, 'na') === 0) {
            return 'None';
        }

        return $suffix;
    }

    private function normalizeSuffix(?string $suffix): string
    {
        $suffix = trim((string) ($suffix ?? ''));

        if ($suffix === '' || $suffix === '—' || $suffix === '-'
            || strcasecmp($suffix, 'none') === 0
            || strcasecmp($suffix, 'n/a') === 0
            || strcasecmp($suffix, 'na') === 0) {
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(User $user, array $data): array
    {
        abort_unless($user->canAccessFederationPortal() && (int) $user->tenant_id > 0, 403);

        $barangayName = trim((string) ($data['barangay'] ?? ''));
        $barangay = Barangay::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('name', $barangayName)
            ->first();

        if (! $barangay) {
            abort(404, 'Barangay not found.');
        }

        $data['barangay'] = $barangay->name;
        $data['region'] = $data['region'] ?: ($barangay->region ?: 'Region IV-A (CALABARZON)');
        $data['province'] = $data['province'] ?: ($barangay->province ?: 'Laguna');
        $data['city'] = $data['city'] ?: ($barangay->municipality ?: 'Santa Cruz');
        $formData = $this->buildFormDataFromRequest($data);
        $formData['entry_source'] = 'federation_monitoring';
        $formData['monitoring_imported_at'] = now()->toIso8601String();
        $formData['monitoring_imported_by'] = (int) $user->id;

        $registration = KabataanRegistration::create([
            'tenant_id' => $barangay->tenant_id,
            'barangay_id' => $barangay->id,
            'last_name' => $data['last_name'],
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'suffix' => $data['suffix'] ?? null,
            'email' => $data['email'] ?? null,
            'contact_number' => $data['contact_number'] ?? null,
            'form_data' => $formData,
            'status' => 'active',
            'evaluation_status' => 'active',
            'submitted_at' => now(),
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
        ]);

        $this->auditLog->log('kabataan_registration.created', $user, [
            'action' => 'create',
            'entity_type' => 'kabataan_registration',
            'entity_id' => (string) $registration->id,
            'module' => 'kabataan_monitoring',
            'barangay_id' => (int) $barangay->id,
        ]);

        $this->forgetListCache($barangayName, (int) $user->tenant_id);

        return [
            'id' => $registration->id,
            'respondent_number' => $this->displayRespondentNumber($registration),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildFormDataFromRequest(array $data): array
    {
        return [
            'entry_source' => 'federation_monitoring',
            'age' => $data['age'] ?? null,
            'birthday' => $data['birthday'] ?? null,
            'sex' => $data['sex'] ?? null,
            'purok_zone' => $data['purok_zone'] ?? null,
            'civil_status' => $data['civil_status'] ?? null,
            'youth_classification' => $data['youth_classification'] ?? null,
            'youth_age_group' => $data['youth_age_group'] ?? null,
            'work_status' => $data['work_status'] ?? null,
            'education' => $data['education'] ?? null,
            'sk_voter' => $data['sk_voter'] ?? null,
            'national_voter' => $data['national_voter'] ?? null,
            'sk_voted' => $data['sk_voted'] ?? null,
            'kk_assembly' => $data['kk_assembly'] ?? null,
            'kk_times' => $data['kk_times'] ?? null,
            'kk_reason' => $data['kk_reason'] ?? null,
            'facebook' => $data['facebook'] ?? null,
            'group_chat' => $data['group_chat'] ?? null,
            'signature' => $data['signature'] ?? null,
            'region' => $data['region'] ?? 'Region IV-A (CALABARZON)',
            'province' => $data['province'] ?? 'Laguna',
            'city' => $data['city'] ?? 'Santa Cruz',
            'barangay' => $data['barangay'] ?? null,
        ];
    }
}
