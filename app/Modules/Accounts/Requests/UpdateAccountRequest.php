<?php

namespace App\Modules\Accounts\Requests;

use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Support\SkOfficialTermDates;
use App\Modules\Shared\Models\User;
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
                if (($this->route('user')?->role ?? '') === User::ROLE_SK_OFFICIAL && $field !== 'first_name') {
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
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $accountRole = (string) ($this->route('user')?->role ?? '');
        $requiresDemographics = in_array($accountRole, [
            User::ROLE_SK_FED,
            User::ROLE_SK_OFFICIAL,
        ], true);
        $ageMin = 15;
        $ageMax = 30;
        $isOfficial = $accountRole === User::ROLE_SK_OFFICIAL;

        $nameRules = $isOfficial
            ? ['required', 'string', 'min:3', 'max:50', 'regex:/^(?!\s)[A-Z.\-]+(?: [A-Z.\-]+)?$/u']
            : ['required', 'string', 'max:100'];
        $middleNameRules = $isOfficial
            ? ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[A-Z\-\']+$/u']
            : ['nullable', 'string', 'max:100'];
        $lastNameRules = $isOfficial
            ? ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Z\-\']+$/u']
            : ['required', 'string', 'max:100'];

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
                'max:4',
                'regex:/^\S+$/u',
            ],
            'sex' => [$requiresDemographics ? 'required' : 'nullable', Rule::in(['Male', 'Female'])],
            'date_of_birth' => [$requiresDemographics ? 'required' : 'nullable', 'date', 'before:today'],
            'age' => [$requiresDemographics ? 'required' : 'nullable', 'integer', 'min:'.$ageMin, 'max:'.$ageMax],
            'contact_number' => [$requiresDemographics ? 'required' : 'nullable', 'regex:/^09\d{9}$/'],
            'email' => [
                'required',
                'email',
                'max:255',
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
                'after_or_equal:'.($accountRole === User::ROLE_SK_OFFICIAL
                    ? SkOfficialTermDates::FIRST_START
                    : '2023-01-01'),
                'before_or_equal:'.Carbon::now()->toDateString(),
            ],
            'term_end' => ['required', 'date', 'after:term_start'],
            'term_status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'EXPIRED', 'REPLACED'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $accountRole = (string) ($this->route('user')?->role ?? '');
            if ($accountRole !== User::ROLE_SK_OFFICIAL) {
                return;
            }

            $suffix = $this->input('suffix');
            $allowedSuffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];
            if ($suffix !== null && $suffix !== '' && $suffix !== '__other__' && $suffix !== 'NONE' && ! in_array($suffix, $allowedSuffixes, true)) {
                if (strlen((string) $suffix) < 1 || strlen((string) $suffix) > 4 || preg_match('/\s/u', (string) $suffix)) {
                    $validator->errors()->add('suffix_other', 'Other suffix must be 1-4 characters with no spaces.');
                }
            }

            $termStart = $this->input('term_start');
            $termEnd = $this->input('term_end');

            if (! is_string($termStart) || ! is_string($termEnd) || $termStart === '' || $termEnd === '') {
                return;
            }

            foreach (SkOfficialTermDates::errorsFor($termStart, $termEnd) as $field => $message) {
                $validator->errors()->add($field, $message);
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
            'last_name.regex' => 'Last name must use uppercase letters only, with no spaces.',
            'middle_name.regex' => 'Middle name must use uppercase letters only, with no spaces.',
            'date_of_birth.before' => 'Birthdate must be before today.',
            'age.min' => 'Age must be at least 15.',
            'age.max' => 'Age must not exceed 30.',
            'contact_number.regex' => 'Contact number must be 11 digits starting with 09.',
            'suffix_other.max' => 'Other suffix must not exceed 4 characters.',
            'region.in' => 'Region must be IV-A CALABARZON.',
            'province.in' => 'Province must be Laguna.',
            'municipality.in' => 'Municipality must be Santa Cruz.',
            'term_start.after_or_equal' => (string) ($this->route('user')?->role) === User::ROLE_SK_OFFICIAL
                ? SkOfficialTermDates::startRuleMessage()
                : 'Term start date must be on or after January 1, 2023.',
            'term_start.before_or_equal' => (string) ($this->route('user')?->role) === User::ROLE_SK_OFFICIAL
                ? 'Term start date cannot be in the future. Use the current SK term that began on November 30.'
                : 'Term start date cannot be in the future.',
            'term_end.after' => (string) ($this->route('user')?->role) === User::ROLE_SK_OFFICIAL
                ? SkOfficialTermDates::endRuleMessage()
                : 'Term end date must be after the term start date.',
        ];
    }
}
