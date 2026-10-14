<?php

namespace App\Modules\Accounts\Requests;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Support\SkOfficialTermDates;
use App\Modules\Shared\Models\User;
use App\Rules\PhilippineMobileNumber;
use App\Rules\ValidEmailAddress;
use App\Services\PhoneNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFederationAdministrator() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $suffix = $this->input('suffix');

        if ($suffix === 'NONE' || $suffix === 'None') {
            $this->merge(['suffix' => 'NONE']);
        } elseif ($suffix === '__other__') {
            // Keep __other__ so Rule::in passes; resolve to custom value after validation.
        } elseif ($suffix === '') {
            $this->merge(['suffix' => 'NONE']);
        }

        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }

        foreach (['first_name', 'last_name', 'middle_name'] as $field) {
            if ($this->filled($field)) {
                $value = mb_strtoupper(trim((string) $this->input($field)), 'UTF-8');
                $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
                $this->merge([$field => $value]);
            }
        }

        if ($this->filled('contact_number')) {
            $this->merge([
                'contact_number' => trim((string) $this->input('contact_number')),
            ]);
        }

        foreach (['date_of_birth', 'term_start', 'term_end'] as $field) {
            $value = trim((string) $this->input($field, ''));
            if ($value === '') {
                continue;
            }

            foreach (['m/d/Y', 'n/j/Y', 'm/d/y', 'n/j/y'] as $format) {
                try {
                    $parsed = Carbon::createFromFormat($format, $value);
                    if ($parsed !== false) {
                        $this->merge([$field => $parsed->format('Y-m-d')]);
                        break;
                    }
                } catch (\Throwable) {
                    // Try next accepted US date format.
                }
            }
        }

        if ($this->filled('date_of_birth')) {
            try {
                $this->merge([
                    'age' => Carbon::parse((string) $this->input('date_of_birth'))->age,
                ]);
            } catch (\Throwable) {
                // Keep submitted age; validation will catch invalid DOB.
            }
        }

        // Non-President SK Officials inherit locked President term dates.
        $account = $this->route('user');
        if ($account instanceof User && $account->role === User::ROLE_SK_OFFICIAL) {
            $account->loadMissing('officialProfile');
            $isPresident = strcasecmp(trim((string) ($account->officialProfile?->federation_position ?? '')), 'President') === 0;
            if (! $isPresident) {
                $tenantId = (int) ($this->user()?->tenant_id ?? $account->tenant_id ?? 0);
                $presidentTerms = $tenantId > 0 ? SkOfficialTermDates::presidentTermDates($tenantId) : null;
                if ($presidentTerms !== null) {
                    $this->merge([
                        'term_start' => $presidentTerms['term_start'],
                        'term_end' => $presidentTerms['term_end'],
                    ]);
                }
            }
        }
    }

    /**
     * True when the submitted birthdate is byte-identical to the stored one.
     *
     * Officials saved before the age window was corrected can now fall outside
     * 18-30. Exempting only an untouched birthdate keeps those accounts editable
     * without opening a path to save a new out-of-range birthdate.
     */
    private function birthdateMatchesStored(): bool
    {
        $account = $this->route('user');
        if (! $account instanceof User) {
            return false;
        }

        $account->loadMissing('officialProfile');
        $stored = $account->officialProfile?->date_of_birth;
        if ($stored === null) {
            return false;
        }

        $submitted = $this->input('date_of_birth');
        if (! is_string($submitted) || trim($submitted) === '') {
            return false;
        }

        try {
            return Carbon::parse($submitted)->startOfDay()->toDateString()
                === Carbon::parse($stored)->startOfDay()->toDateString();
        } catch (\Throwable) {
            return false;
        }
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $accountRole = (string) ($this->route('user')?->role ?? '');
        $requiresDemographics = in_array($accountRole, [
            User::ROLE_SK_FED,
            User::ROLE_SK_OFFICIAL,
        ], true);
        $isOfficial = $accountRole === User::ROLE_SK_OFFICIAL;

        $bounds = match ($accountRole) {
            User::ROLE_SK_FED => SkOfficialTermDates::federationBirthdateBounds(),
            User::ROLE_SK_OFFICIAL => SkOfficialTermDates::officialAccountBirthdateBounds(),
            default => SkOfficialTermDates::birthdateBounds(),
        };
        $keepsExistingBirthdate = $requiresDemographics && $this->birthdateMatchesStored();

        $dateOfBirthRules = $requiresDemographics
            ? ['required', 'date', 'before:today']
            : ['nullable', 'date', 'before:today'];
        if ($requiresDemographics && ! $keepsExistingBirthdate) {
            $dateOfBirthRules[] = 'after_or_equal:'.$bounds['min'];
            $dateOfBirthRules[] = 'before_or_equal:'.$bounds['max'];
        }

        // Age is recomputed from date_of_birth in prepareForValidation, so the
        // birthdate rules above are the only age gate.
        $ageRules = $requiresDemographics
            ? ['required', 'integer', 'min:1', 'max:150']
            : ['nullable', 'integer', 'min:0', 'max:150'];

        $nameMin = $isOfficial ? 2 : 3;
        $nameMax = $isOfficial ? 150 : 100;
        $nameRules = ['required', 'string', 'min:'.$nameMin, 'max:'.$nameMax, 'regex:/^(?!\s)[A-Z.\-\']+(?: [A-Z.\-\']+)*$/u'];
        $middleNameRules = ['nullable', 'string', 'min:'.$nameMin, 'max:'.$nameMax, 'regex:/^(?!\s)[A-Z.\-\']+(?: [A-Z.\-\']+)*$/u'];
        $lastNameRules = $nameRules;

        return [
            'first_name' => $nameRules,
            'last_name' => $lastNameRules,
            'middle_name' => $middleNameRules,
            'suffix' => ['nullable', Rule::in(['NONE', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V', '__other__'])],
            'suffix_other' => [
                Rule::requiredIf(fn (): bool => $this->input('suffix') === '__other__'),
                'nullable',
                'string',
                'min:1',
                'max:5',
                'regex:/^\S+$/u',
            ],
            'sex' => [$requiresDemographics ? 'required' : 'nullable', Rule::in(['Male', 'Female'])],
            'date_of_birth' => $dateOfBirthRules,
            'age' => $ageRules,
            'contact_number' => $requiresDemographics
                ? ['required', 'string', 'max:30', new PhilippineMobileNumber]
                : ['nullable', 'string', 'max:30', new PhilippineMobileNumber],
            'email' => [
                ...ValidEmailAddress::profilingRules(),
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'status' => ['required', Rule::in([
                User::STATUS_ACTIVE,
                User::STATUS_INACTIVE,
                User::STATUS_PENDING_APPROVAL,
                User::STATUS_SUSPENDED,
            ])],
            'region' => ['nullable', Rule::in(['IV-A CALABARZON'])],
            'province' => ['nullable', Rule::in(['Laguna'])],
            'municipality' => ['nullable', Rule::in(['Santa Cruz'])],
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
            'position' => [
                $accountRole === User::ROLE_SK_FED ? 'required' : 'required',
                Rule::in(OfficialProfile::positionsForRole($accountRole !== '' ? $accountRole : User::ROLE_SK_FED)),
            ],
            'federation_position' => ['nullable', Rule::in(OfficialProfile::FEDERATION_POSITIONS)],
            'term_start' => [
                'required',
                'date',
                'after_or_equal:'.SkOfficialTermDates::FIRST_START,
                'before_or_equal:'.SkOfficialTermDates::maxStart(),
            ],
            'term_end' => ['required', 'date', 'after:term_start'],
            'term_status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'EXPIRED', 'REPLACED'])],
        ];
    }

    /**
     * Re-derive eligibility from the submitted birthdate. The age input is never
     * trusted: prepareForValidation overwrites it from date_of_birth.
     */
    private function validateSkAgeEligibility(Validator $validator): void
    {
        $accountRole = (string) ($this->route('user')?->role ?? '');
        $requiresAgeCheck = in_array($accountRole, [
            User::ROLE_SK_FED,
            User::ROLE_SK_OFFICIAL,
        ], true);

        if (! $requiresAgeCheck
            || $validator->errors()->has('date_of_birth')
            || $this->birthdateMatchesStored()
        ) {
            return;
        }

        $dob = $this->input('date_of_birth');
        if ($dob === null || trim((string) $dob) === '') {
            $validator->errors()->add('date_of_birth', SkOfficialTermDates::MSG_DOB_REQUIRED);
            return;
        }

        $error = match ($accountRole) {
            User::ROLE_SK_FED => SkOfficialTermDates::federationBirthdateErrorFor($dob),
            User::ROLE_SK_OFFICIAL => SkOfficialTermDates::officialAccountBirthdateErrorFor($dob),
            default => SkOfficialTermDates::birthdateErrorFor($dob),
        };
        if ($error !== null) {
            $validator->errors()->add('date_of_birth', $error);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateSkAgeEligibility($validator);

            $accountRole = (string) ($this->route('user')?->role ?? '');
            if ($accountRole === User::ROLE_SK_OFFICIAL) {
                $suffix = $this->input('suffix');
                $allowedSuffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];
                if ($suffix !== null && $suffix !== '' && $suffix !== '__other__' && $suffix !== 'NONE' && ! in_array($suffix, $allowedSuffixes, true)) {
                    if (strlen((string) $suffix) < 1 || strlen((string) $suffix) > 5 || preg_match('/\s/u', (string) $suffix)) {
                        $validator->errors()->add('suffix_other', 'Other suffix must be 1-5 characters with no spaces.');
                    }
                }
            }

            $termStart = $this->input('term_start');
            $termEnd = $this->input('term_end');

            if (! is_string($termStart) || ! is_string($termEnd) || $termStart === '' || $termEnd === '') {
                return;
            }

            $tenantId = (int) ($this->user()?->tenant_id ?? 0);
            $leadership = $tenantId > 0 ? SkOfficialTermDates::leadershipDefaults($tenantId) : null;

            foreach (SkOfficialTermDates::errorsFor($termStart, $termEnd, null, $leadership) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });
    }

    /**
     * Resolve __other__ into the stored custom suffix after rules pass.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);
        if ($key !== null) {
            return $data;
        }

        if (($data['suffix'] ?? null) === '__other__') {
            $other = mb_strtoupper(trim((string) ($data['suffix_other'] ?? '')), 'UTF-8');
            $data['suffix'] = $other !== '' ? $other : null;
        }

        if (isset($data['contact_number']) && $data['contact_number'] !== null && $data['contact_number'] !== '') {
            $local = app(PhoneNumberService::class)->toLocalMobile((string) $data['contact_number']);
            if ($local !== null) {
                $data['contact_number'] = $local;
            }
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $accountRole = (string) ($this->route('user')?->role ?? '');

        return [
            'email.unique' => 'This email is already taken.',
            'email.required' => ValidEmailAddress::MSG_REQUIRED,
            'email.max' => ValidEmailAddress::MSG_MAX,
            'first_name.regex' => 'First name must use uppercase letters only; need a letter before each space; no pure spaces.',
            'last_name.regex' => 'Last name must use uppercase letters only; need a letter before each space; no pure spaces.',
            'middle_name.regex' => 'Middle name must use uppercase letters only; need a letter before each space; no pure spaces.',
            'date_of_birth.required' => SkOfficialTermDates::MSG_DOB_REQUIRED,
            'date_of_birth.before' => 'Birthdate must be before today.',
            'date_of_birth.after_or_equal' => match ($accountRole) {
                User::ROLE_SK_FED => SkOfficialTermDates::MSG_BEFORE_FEDERATION_MIN_BIRTHDATE,
                User::ROLE_SK_OFFICIAL => SkOfficialTermDates::MSG_BEFORE_OFFICIAL_MIN_BIRTHDATE,
                default => SkOfficialTermDates::MSG_OVER_MAX_AGE,
            },
            'date_of_birth.before_or_equal' => SkOfficialTermDates::MSG_UNDER_MIN_AGE,
            'age.min' => 'Age must be a valid number.',
            'age.max' => 'Age must be a valid number.',
            'contact_number.required' => PhoneNumberService::MSG_REQUIRED,
            'suffix_other.max' => 'Other suffix must not exceed 5 characters.',
            'suffix_other.min' => 'Other suffix must be at least 1 character.',
            'region.in' => 'Region must be IV-A CALABARZON.',
            'province.in' => 'Province must be Laguna.',
            'municipality.in' => 'Municipality must be Santa Cruz.',
            'term_start.after_or_equal' => SkOfficialTermDates::startRuleMessage(),
            'term_start.before_or_equal' => SkOfficialTermDates::startRuleMessage(),
            'term_end.after' => SkOfficialTermDates::endRuleMessage(),
        ] + ValidEmailAddress::profilingMessages();
    }
}
