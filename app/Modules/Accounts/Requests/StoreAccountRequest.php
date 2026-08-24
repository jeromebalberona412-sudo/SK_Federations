<?php

namespace App\Modules\Accounts\Requests;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Support\SkOfficialTermDates;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAccountRequest extends FormRequest
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
            $other = trim((string) $this->input('suffix_other', ''));
            if ($other !== '') {
                $this->merge(['suffix' => mb_strtoupper($other, 'UTF-8')]);
            }
        } elseif ($suffix === '') {
            $this->merge(['suffix' => null]);
        }

        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }

        foreach (['first_name', 'last_name', 'middle_name'] as $field) {
            if ($this->filled($field)) {
                $value = mb_strtoupper(trim((string) $this->input($field)), 'UTF-8');
                if ($this->input('role') === User::ROLE_SK_OFFICIAL && $field !== 'first_name') {
                    $value = preg_replace('/\s+/u', '', $value) ?? $value;
                }
                $this->merge([$field => $value]);
            }
        }

        if ($this->filled('contact_number')) {
            $digits = preg_replace('/\D+/', '', (string) $this->input('contact_number')) ?? '';
            if (! str_starts_with($digits, '09')) {
                $digits = '09'.ltrim($digits, '0');
            }

            $this->merge(['contact_number' => substr($digits, 0, 11)]);
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

        if (! $this->filled('status')) {
            $this->merge(['status' => User::STATUS_ACTIVE]);
        }
    }

    public function rules(): array
    {
        $requiresDemographics = in_array($this->input('role'), [
            User::ROLE_SK_FED,
            User::ROLE_SK_OFFICIAL,
        ], true);

        $isOfficial = $this->input('role') === User::ROLE_SK_OFFICIAL;
        $isFederation = $this->input('role') === User::ROLE_SK_FED;
        $currentYear = Carbon::now()->year;
        $termStartMin = $isOfficial ? SkOfficialTermDates::FIRST_START : "{$currentYear}-01-01";
        $termStartMax = $isOfficial ? Carbon::now()->toDateString() : "{$currentYear}-12-31";
        $ageMin = 15;
        $ageMax = 30;

        $nameRules = $isOfficial
            ? ['required', 'string', 'min:3', 'max:50', 'regex:/^(?!\s)[A-Z.\-]+(?: [A-Z.\-]+)?$/u']
            : ['required', 'string', 'max:100', 'regex:/^[A-Z\s\-\']+$/u'];

        $middleNameRules = $isOfficial
            ? ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[A-Z\-\']+$/u']
            : ['nullable', 'string', 'max:100', 'regex:/^[A-Z\s\-\']*$/u'];

        $dateOfBirthRules = ($isOfficial || $isFederation)
            ? ['required', 'date', 'before:today']
            : [$requiresDemographics ? 'required' : 'nullable', 'date', 'before:today'];

        $ageRules = ($isOfficial || $isFederation)
            ? ['required', 'integer', 'min:'.$ageMin, 'max:'.$ageMax]
            : ['nullable', 'integer', 'min:0', 'max:150'];

        $emailRules = [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email')->whereNull('deleted_at'),
        ];

        if ($isOfficial) {
            $emailRules[] = 'regex:/^[a-z0-9._%+-]{6,30}@gmail\.com$/i';
        }

        return [
            'first_name' => $nameRules,
            'last_name' => $nameRules,
            'middle_name' => $middleNameRules,
            'suffix' => $isOfficial
                ? ['required', 'string', Rule::in(['NONE', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V', '__other__'])]
                : ['nullable', 'string', 'max:10'],
            'suffix_other' => [
                Rule::requiredIf(fn (): bool => $this->input('suffix') === '__other__'),
                'nullable',
                'string',
                'min:1',
                'max:4',
                'regex:/^\S+$/u',
            ],
            'sex' => [$requiresDemographics ? 'required' : 'nullable', Rule::in(['Male', 'Female']), 'not_in:'],
            'date_of_birth' => $dateOfBirthRules,
            'age' => $ageRules,
            'contact_number' => [$requiresDemographics ? 'required' : 'nullable', 'regex:/^09\d{9}$/'],
            'email' => $emailRules,
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([
                User::ROLE_SK_FED,
                User::ROLE_SK_OFFICIAL,
            ]), 'not_in:'],
            'status' => ['required', Rule::in([
                User::STATUS_ACTIVE,
                User::STATUS_INACTIVE,
                User::STATUS_PENDING_APPROVAL,
                User::STATUS_SUSPENDED,
            ]), 'not_in:'],
            'region' => ['required', Rule::in(['IV-A CALABARZON'])],
            'province' => ['required', Rule::in(['Laguna'])],
            'municipality' => ['required', Rule::in(['Santa Cruz'])],
            'barangay_id' => ['required', 'integer', 'exists:barangays,id', 'not_in:'],
            'position' => ['required', Rule::in(OfficialProfile::positionsForRole((string) $this->input('role'))), 'not_in:'],
            'term_start' => ['required', 'date', 'after_or_equal:'.$termStartMin, 'before_or_equal:'.$termStartMax],
            'term_end' => ['required', 'date', 'after:term_start'],
            'term_status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'EXPIRED', 'REPLACED'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('role') === User::ROLE_SK_OFFICIAL) {
                $suffix = $this->input('suffix');
                $allowedSuffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];
                if ($suffix !== null && $suffix !== '' && $suffix !== '__other__' && $suffix !== 'NONE' && ! in_array($suffix, $allowedSuffixes, true)) {
                    if (strlen((string) $suffix) < 1 || strlen((string) $suffix) > 4 || preg_match('/\s/u', (string) $suffix)) {
                        $validator->errors()->add('suffix_other', 'Other suffix must be 1-4 characters with no spaces.');
                    }
                }
            }

            $termStart = $this->input('term_start');
            $termEnd = $this->input('term_end');

            if (! is_string($termStart) || ! is_string($termEnd) || $termStart === '' || $termEnd === '') {
                return;
            }

            if ($this->input('role') === User::ROLE_SK_OFFICIAL) {
                foreach (SkOfficialTermDates::errorsFor($termStart, $termEnd) as $field => $message) {
                    $validator->errors()->add($field, $message);
                }

                return;
            }

            try {
                $start = Carbon::parse($termStart)->startOfDay();
                $end = Carbon::parse($termEnd)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            if ($end->ne($start->copy()->addYears(4))) {
                $validator->errors()->add(
                    'term_end',
                    'Term end date must be exactly 4 years after the term start date.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already taken.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'first_name.regex' => 'First name must use uppercase letters only, with at most one space and no leading spaces.',
            'first_name.min' => 'First name must be at least 3 characters.',
            'first_name.max' => 'First name must not exceed 50 characters.',
            'last_name.regex' => 'Last name must use uppercase letters only, with no spaces.',
            'last_name.min' => 'Last name must be at least 3 characters.',
            'last_name.max' => 'Last name must not exceed 50 characters.',
            'middle_name.regex' => 'Middle name must use uppercase letters only, with no spaces.',
            'middle_name.min' => 'Middle name must be at least 3 characters when provided.',
            'middle_name.max' => 'Middle name must not exceed 50 characters.',
            'email.regex' => 'Email must be a @gmail.com address with 6-30 characters before @.',
            'date_of_birth.before' => 'Birthdate must be before today.',
            'age.min' => 'Age must be at least 15.',
            'age.max' => 'Age must not exceed 30.',
            'suffix_other.regex' => 'Other suffix must not contain spaces.',
            'suffix_other.max' => 'Other suffix must not exceed 4 characters.',
            'contact_number.regex' => 'Contact number must be 11 digits starting with 09.',
            'region.in' => 'Region must be IV-A CALABARZON.',
            'province.in' => 'Province must be Laguna.',
            'municipality.in' => 'Municipality must be Santa Cruz.',
            'term_start.after_or_equal' => $this->input('role') === User::ROLE_SK_OFFICIAL
                ? SkOfficialTermDates::startRuleMessage()
                : 'Term start date must be within the current year.',
            'term_start.before_or_equal' => $this->input('role') === User::ROLE_SK_OFFICIAL
                ? 'Term start date cannot be in the future. Use the current SK term that began on November 30.'
                : 'Term start date must be within the current year.',
            'term_end.after' => $this->input('role') === User::ROLE_SK_OFFICIAL
                ? SkOfficialTermDates::endRuleMessage()
                : 'Term end date must be exactly 4 years after the term start date.',
        ];
    }
}
