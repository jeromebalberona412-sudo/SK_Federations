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
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $accountRole = (string) ($this->route('user')?->role ?? '');
        $requiresDemographics = in_array($accountRole, [
            User::ROLE_SK_FED,
            User::ROLE_SK_OFFICIAL,
        ], true);
        $ageMin = 18;
        $ageMax = 24;
        $isOfficial = $accountRole === User::ROLE_SK_OFFICIAL;
        // Update must allow existing officials who aged past 24; create still enforces 18–24.
        $dateOfBirthRules = $requiresDemographics
            ? ['required', 'date', 'before:today']
            : ['nullable', 'date', 'before:today'];
        $ageRules = $requiresDemographics
            ? ['required', 'integer', 'min:1', 'max:120']
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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
        return [
            'email.unique' => 'This email is already taken.',
            'email.required' => ValidEmailAddress::MSG_REQUIRED,
            'email.max' => ValidEmailAddress::MSG_MAX,
            'first_name.regex' => 'First name must use uppercase letters only; need a letter before each space; no pure spaces.',
            'last_name.regex' => 'Last name must use uppercase letters only; need a letter before each space; no pure spaces.',
            'middle_name.regex' => 'Middle name must use uppercase letters only; need a letter before each space; no pure spaces.',
            'date_of_birth.before' => 'Birthdate must be before today.',
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
