<?php

namespace App\Modules\Accounts\Requests;

use App\Modules\Accounts\Models\OfficialProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignFederationPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFederationAdministrator() ?? false;
    }

    public function rules(): array
    {
        return [
            'federation_position' => ['nullable', 'string', Rule::in(OfficialProfile::FEDERATION_POSITIONS)],
        ];
    }
}
