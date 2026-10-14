<?php

namespace App\Requests;

use App\Rules\PhilippineMobileNumber;
use App\Rules\ValidEmailAddress;
use App\Services\PhoneNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class UpdateKkProfilingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'first_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'middle_name' => ['nullable', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z.\-\s]+$/'],
            'suffix' => ['required', 'string', 'in:None,Jr.,Sr.,I,II,III,IV,V,Others'],
            'custom_suffix' => ['nullable', 'required_if:suffix,Others', 'string', 'max:5', 'regex:/^(?!\s+$)[A-Za-z.\s]+$/'],
            'email' => ['required', ValidEmailAddress::profilingRules()],
            'contact_number' => ['nullable', 'string', 'max:30', new PhilippineMobileNumber($this->route('id'), false)],
            'age' => ['required', 'integer', 'min:15', 'max:30'],
            'birthday' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['nullable', 'in:Male,Female'],
            'purok_zone' => ['nullable', 'string', 'max:100'],
            'civil_status' => ['nullable', 'string'],
            'youth_classification' => ['nullable', 'string'],
            'youth_age_group' => ['nullable', 'string'],
            'work_status' => ['nullable', 'string'],
            'education' => ['nullable', 'string'],
            'sk_voter' => ['nullable', 'string'],
            'national_voter' => ['nullable', 'string'],
            'sk_voted' => ['nullable', 'string'],
            'kk_assembly' => ['nullable', 'string', 'in:Yes,No'],
            'kk_times' => ['nullable', 'required_if:kk_assembly,Yes', 'string'],
            'kk_reason' => ['nullable', 'required_if:kk_assembly,No', 'string'],
            'facebook' => ['nullable', 'string', 'max:80'],
            'facebook_profile_url' => ['nullable', 'string', 'max:80'],
            'group_chat' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'last_name.required' => 'Please enter a valid last name.',
            'last_name.min' => 'Last name must be at least 2 characters.',
            'last_name.max' => 'Last name must not exceed 150 characters.',
            'last_name.regex' => 'Last name contains invalid characters.',
            'first_name.required' => 'Please enter a valid first name.',
            'first_name.min' => 'First name must be at least 2 characters.',
            'first_name.max' => 'First name must not exceed 150 characters.',
            'first_name.regex' => 'First name contains invalid characters.',
            'middle_name.min' => 'Middle name must be at least 2 characters.',
            'middle_name.max' => 'Middle name must not exceed 150 characters.',
            'middle_name.regex' => 'Middle name contains invalid characters.',
            'suffix.required' => 'Please select a suffix.',
            'suffix.in' => 'Invalid suffix selected.',
            'custom_suffix.required_if' => 'Please specify the custom suffix.',
            'custom_suffix.max' => 'Custom suffix must not exceed 5 characters.',
            'custom_suffix.regex' => 'Custom suffix contains invalid characters.',
            'email.required' => 'E-mail address is required.',
            'email.max' => ValidEmailAddress::MSG_MAX,
            'contact_number.max' => 'Contact number must not exceed 30 characters.',
            'age.required' => 'Age is required.',
            'age.integer' => 'Age must be a valid number.',
            'age.min' => 'Age must be at least 15.',
            'age.max' => 'Age must not exceed 30.',
            'birthday.required' => 'Birthday is required.',
            'birthday.date' => 'Please enter a valid birthday.',
            'birthday.before_or_equal' => 'Birthday cannot be in the future.',
            'sex.required' => 'Please select sex assigned at birth.',
            'sex.in' => 'Invalid sex value.',
            'civil_status.required' => 'Please select civil status.',
            'youth_classification.required' => 'Please select youth classification.',
            'youth_age_group.required' => 'Please select youth age group.',
            'work_status.required' => 'Please select work status.',
            'education.required' => 'Please select educational attainment.',
            'sk_voter.required' => 'Please select SK voter status.',
            'national_voter.required' => 'Please select national voter status.',
            'sk_voted.required' => 'Please select voting history.',
            'kk_assembly.required' => 'Please select KK Assembly attendance.',
            'kk_assembly.in' => 'Invalid KK Assembly value.',
            'kk_times.required_if' => 'Please specify how many times you attended KK Assembly.',
            'kk_reason.required_if' => 'Please specify why you did not attend KK Assembly.',
        ] + ValidEmailAddress::profilingMessages();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'last_name' => preg_replace('/\s+/', ' ', trim((string) $this->input('last_name', ''))) ?: '',
            'first_name' => preg_replace('/\s+/', ' ', trim((string) $this->input('first_name', ''))) ?: '',
            'middle_name' => preg_replace('/\s+/', ' ', trim((string) $this->input('middle_name', ''))) ?: null,
            'contact_number' => trim((string) $this->input('contact_number', '')) === '09'
                ? null
                : $this->input('contact_number'),
        ]);

        if ($this->input('middle_name') === '') {
            $this->merge(['middle_name' => null]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $this->validateContactNumber($validator);
            $this->validateCustomSuffix($validator);
            $this->validateAgeBirthdayConsistency($validator);
        });
    }

    /**
     * Validate contact number using PhoneNumberService.
     */
    protected function validateContactNumber(Validator $validator): void
    {
        $contactNumber = $this->input('contact_number');
        if ($contactNumber) {
            $result = app(PhoneNumberService::class)->validatePhilippineMobile(
                is_string($contactNumber) || is_numeric($contactNumber) ? (string) $contactNumber : null,
                $this->route('id'),
                false
            );

            if (! $result['ok']) {
                $validator->errors()->add('contact_number', $result['error'] ?? PhoneNumberService::MSG_INVALID);
            } else {
                $this->merge(['contact_number' => $result['canonical']]);
            }
        }
    }

    /**
     * Validate custom suffix when "Others" is selected.
     */
    protected function validateCustomSuffix(Validator $validator): void
    {
        $suffix = $this->input('suffix');
        if ($suffix === 'Others') {
            $customSuffix = trim((string) ($this->input('custom_suffix') ?? ''));
            $compact = strtoupper(str_replace(' ', '', $customSuffix));
            $validRoman = in_array($compact, ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'], true);
            $validText = (bool) preg_match('/^[A-Za-z.]+$/', str_replace(' ', '', $customSuffix));

            if (! $validRoman && ! $validText) {
                $validator->errors()->add('custom_suffix', 'Only text and valid Roman numeral suffixes are allowed.');
            }

            if (strlen(str_replace(' ', '', $customSuffix)) > 5) {
                $validator->errors()->add('custom_suffix', 'Suffix must not exceed 5 characters.');
            }
        }
    }

    /**
     * Validate age and birthday consistency.
     */
    protected function validateAgeBirthdayConsistency(Validator $validator): void
    {
        $birthday = $this->input('birthday');
        $age = $this->input('age');

        if ($birthday && $age) {
            try {
                $derivedAge = Carbon::parse($birthday)->age;
                if ($derivedAge < 15 || $derivedAge > 30 || (int) $age !== (int) $derivedAge) {
                    $validator->errors()->add('birthday', 'Birthday and age must match and be within 15 to 30 years old.');
                }
            } catch (\Throwable $e) {
                $validator->errors()->add('birthday', 'Invalid birthday value.');
            }
        }
    }
}
