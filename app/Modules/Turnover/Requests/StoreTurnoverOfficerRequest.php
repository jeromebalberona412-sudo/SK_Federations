<?php

namespace App\Modules\Turnover\Requests;

use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTurnoverOfficerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasFederationLeadershipAccess() ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['president', 'vice_president'] as $section) {
            $payload = (array) $this->input($section, []);

            foreach (['first_name', 'last_name', 'middle_name'] as $field) {
                if (! empty($payload[$field])) {
                    $value = mb_strtoupper(trim((string) $payload[$field]), 'UTF-8');
                    if ($field !== 'first_name') {
                        $value = preg_replace('/\s+/u', '', $value) ?? $value;
                    }
                    $payload[$field] = $value;
                }
            }

            if (! empty($payload['email'])) {
                $payload['email'] = strtolower(trim((string) $payload['email']));
            }

            if (! empty($payload['contact_number'])) {
                $digits = preg_replace('/\D+/', '', (string) $payload['contact_number']) ?? '';
                if (! str_starts_with($digits, '09')) {
                    $digits = '09'.ltrim($digits, '0');
                }
                $payload['contact_number'] = substr($digits, 0, 11);
            }

            if (($payload['suffix'] ?? '') === '__other__' && ! empty($payload['suffix_other'])) {
                $payload['suffix'] = mb_strtoupper(trim((string) $payload['suffix_other']), 'UTF-8');
            } elseif (($payload['suffix'] ?? '') === '' || ($payload['suffix'] ?? '') === 'None') {
                $payload['suffix'] = null;
            }

            $payload['role'] = User::ROLE_SK_OFFICIAL;
            $payload['position'] = 'Chairperson';
            $payload['term_status'] = OfficialTerm::STATUS_INACTIVE;
            $payload['status'] = User::STATUS_INACTIVE;

            $this->merge([$section => $payload]);
        }
    }

    public function rules(): array
    {
        $today = now()->toDateString();
        $minBirthdate = Carbon::now()->subYears(24)->format('Y-m-d');
        $maxBirthdate = Carbon::now()->subYears(18)->format('Y-m-d');

        $officerRules = [
            'first_name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^(?!\s)[A-Z.\-]+(?: [A-Z.\-]+)?$/u'],
            'last_name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Z\-\']+$/u'],
            'middle_name' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[A-Z\-\']+$/u'],
            'suffix' => ['required', 'string', Rule::in(['NONE', 'Jr.', 'Sr.', 'II', 'III', 'IV', 'V', '__other__'])],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['required', 'date', 'after_or_equal:'.$minBirthdate, 'before_or_equal:'.$maxBirthdate],
            'age' => ['required', 'integer', 'min:18', 'max:24'],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'email' => ['required', 'email', 'max:255', 'regex:/^[a-z0-9._%+-]{6,30}@gmail\.com$/i'],
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
            'term_start' => ['required', 'date', 'after_or_equal:'.$today],
            'term_end' => ['required', 'date', 'after:term_start'],
        ];

        $merged = [];

        foreach ($officerRules as $key => $rules) {
            $merged["president.{$key}"] = $key === 'email'
                ? array_merge($rules, [Rule::unique('users', 'email')->whereNull('deleted_at')])
                : $rules;
            $merged["vice_president.{$key}"] = $key === 'email'
                ? array_merge($rules, [Rule::unique('users', 'email')->whereNull('deleted_at')])
                : $rules;
        }

        $merged['president.suffix_other'] = [
            Rule::requiredIf(fn (): bool => $this->input('president.suffix') === '__other__'),
            'nullable', 'string', 'min:1', 'max:10', 'regex:/^\S+$/u',
        ];
        $merged['vice_president.suffix_other'] = [
            Rule::requiredIf(fn (): bool => $this->input('vice_president.suffix') === '__other__'),
            'nullable', 'string', 'min:1', 'max:10', 'regex:/^\S+$/u',
        ];

        return $merged;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['president', 'vice_president'] as $section) {
                $this->validateOfficerConsistency($validator, $section);
            }

            $presidentEmail = strtolower((string) $this->input('president.email', ''));
            $viceEmail = strtolower((string) $this->input('vice_president.email', ''));

            if ($presidentEmail !== '' && $presidentEmail === $viceEmail) {
                $validator->errors()->add('vice_president.email', 'President and Vice President must use different email addresses.');
            }
        });
    }

    private function validateOfficerConsistency(Validator $validator, string $section): void
    {
        $prefix = $section;
        $dob = $this->input("{$prefix}.date_of_birth");
        $age = $this->input("{$prefix}.age");
        $termStart = $this->input("{$prefix}.term_start");
        $termEnd = $this->input("{$prefix}.term_end");

        if (is_string($dob) && $dob !== '' && is_numeric($age)) {
            try {
                $computedAge = Carbon::parse($dob)->age;
                if ((int) $age !== $computedAge) {
                    $validator->errors()->add("{$prefix}.age", 'Age must match the date of birth.');
                }
            } catch (\Throwable) {
                //
            }
        }

        if (! is_string($termStart) || ! is_string($termEnd) || $termStart === '' || $termEnd === '') {
            return;
        }

        try {
            $start = Carbon::parse($termStart)->startOfDay();
            $end = Carbon::parse($termEnd)->startOfDay();
            $expectedEnd = $start->copy()->addYears(4);

            if (! $start->greaterThanOrEqualTo(now()->startOfDay())) {
                $validator->errors()->add("{$prefix}.term_start", 'Term start cannot be in the past.');

                return;
            }

            if (! $end->equalTo($expectedEnd)) {
                $validator->errors()->add("{$prefix}.term_end", 'Term end must be exactly 4 years after term start.');
            }
        } catch (\Throwable) {
            //
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'president.first_name.regex' => 'First name must use uppercase letters only, with at most one space.',
            'vice_president.first_name.regex' => 'First name must use uppercase letters only, with at most one space.',
            'president.last_name.regex' => 'Last name must be uppercase with no spaces (3-50 characters).',
            'vice_president.last_name.regex' => 'Last name must be uppercase with no spaces (3-50 characters).',
            'president.middle_name.regex' => 'Middle name must be uppercase with no spaces (3-50 characters).',
            'vice_president.middle_name.regex' => 'Middle name must be uppercase with no spaces (3-50 characters).',
            'president.email.regex' => 'Email must be @gmail.com with 6-30 characters before @.',
            'vice_president.email.regex' => 'Email must be @gmail.com with 6-30 characters before @.',
            'president.contact_number.regex' => 'Contact number must be 11 digits starting with 09.',
            'vice_president.contact_number.regex' => 'Contact number must be 11 digits starting with 09.',
            'president.term_start.after_or_equal' => 'Term start cannot be in the past.',
            'vice_president.term_start.after_or_equal' => 'Term start cannot be in the past.',
        ];
    }
}
