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
            'federation_position' => ['required', 'string', Rule::in(OfficialProfile::FEDERATION_POSITIONS)],
        ];
    }

    public function messages(): array
    {
        return [
            'federation_position.required' => 'Please select a federation position.',
            'federation_position.in' => 'Please choose a valid SK Federation position.',
        ];
    }
}
