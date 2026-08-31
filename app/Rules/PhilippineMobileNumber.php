<?php

namespace App\Rules;

use App\Services\PhoneNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhilippineMobileNumber implements ValidationRule
{
    public function __construct(
        protected ?int $ignoreRegistrationId = null,
        protected bool $checkDuplicate = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = app(PhoneNumberService::class)->validatePhilippineMobile(
            is_string($value) || is_numeric($value) ? (string) $value : null,
            $this->ignoreRegistrationId,
            $this->checkDuplicate,
        );

        if (! $result['ok']) {
            $fail($result['error'] ?? PhoneNumberService::MSG_INVALID);
        }
    }
}
