<?php

namespace App\Modules\Accounts\Requests;

use App\Modules\Shared\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchStoreAccountsRequest extends FormRequest
{
    public const MAX_ACCOUNTS = 260;

    public function authorize(): bool
    {
        return $this->user()?->isFederationAdministrator() ?? false;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in([
                User::ROLE_SK_OFFICIAL,
                User::ROLE_SK_FED,
            ])],
            'accounts' => ['required', 'array', 'min:1', 'max:'.self::MAX_ACCOUNTS],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['nullable', 'string', 'max:100'],
            'accounts.*.first_name' => ['nullable', 'string', 'max:100'],
            'accounts.*.middle_name' => ['nullable', 'string', 'max:100'],
            'accounts.*.last_name' => ['nullable', 'string', 'max:100'],
            'accounts.*.suffix' => ['nullable', 'string', 'max:10'],
            'accounts.*.suffix_input' => ['nullable', 'string', 'max:20'],
            'accounts.*.sex' => ['nullable', 'string', 'max:20'],
            'accounts.*.date_of_birth' => ['nullable', 'string', 'max:50'],
            'accounts.*.birthdate' => ['nullable', 'string', 'max:50'],
            'accounts.*.age' => ['nullable'],
            'accounts.*.contact_number' => ['nullable', 'string', 'max:20'],
            'accounts.*.email' => ['nullable', 'string', 'max:254'],
            'accounts.*.position' => ['nullable', 'string', 'max:100'],
            'accounts.*.status' => ['nullable', 'string', 'max:50'],
            'accounts.*.barangay' => ['nullable', 'string', 'max:100'],
            'accounts.*.barangay_name' => ['nullable', 'string', 'max:100'],
            'accounts.*.barangay_id' => ['nullable'],
            'accounts.*.term_start' => ['nullable', 'string', 'max:50'],
            'accounts.*.term_end' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'accounts.max' => 'You can import up to '.self::MAX_ACCOUNTS.' accounts per batch upload.',
        ];
    }
}
