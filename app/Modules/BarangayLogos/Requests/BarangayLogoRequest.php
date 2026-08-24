<?php

namespace App\Modules\BarangayLogos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BarangayLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
            'logo'        => ['required', 'file', 'image', 'mimes:jpeg,png,gif,webp,svg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.max'   => 'Logo must not exceed 2 MB.',
            'logo.mimes' => 'Logo must be a JPEG, PNG, GIF, WebP, or SVG file.',
            'logo.image' => 'The uploaded file must be an image.',
        ];
    }
}
