<?php

namespace App\Modules\Accounts\Requests;

use App\Modules\Accounts\Models\OfficialTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExtendTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFederationAdministrator() ?? false;
    }

    public function rules(): array
    {
        return [
            'term_start' => ['required', 'date'],
            'term_end' => ['required', 'date', 'after:term_start'],
            'status' => ['required', Rule::in(OfficialTerm::STATUSES)],
        ];
    }
}
