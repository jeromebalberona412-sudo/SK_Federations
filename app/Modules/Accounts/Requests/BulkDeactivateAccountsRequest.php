<?php

namespace App\Modules\Accounts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeactivateAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFederationAdministrator() ?? false;
    }

    public function rules(): array
    {
        return [
            'account_ids' => ['required', 'array', 'min:1', 'max:100'],
            'account_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
