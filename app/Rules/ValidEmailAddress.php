<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

/**
 * RFC format + DNS domain checks via Laravel (egulias/email-validator).
 * No hardcoded domain whitelist — any resolvable mail domain may pass.
 *
 * KK Profiling practical limits (not RFC SMTP 254):
 * - Local-part (before @): min 6, max 30 (aligned with major providers such as Gmail)
 * - Complete email: max 64 (practical deliverability for PH mail systems)
 */
class ValidEmailAddress implements ValidationRule
{
    public const MSG_FORMAT = 'Please enter a valid email address.';

    public const MSG_DOMAIN = 'Please enter a valid email address.';

    public const MSG_LOCAL_MIN = 'Email username must be at least 6 characters.';

    public const MSG_LOCAL_MAX = 'Email username must not exceed 30 characters.';

    public const MSG_MAX = 'Email must not exceed 64 characters.';

    public const MSG_REQUIRED = 'Email is required.';

    /** Minimum characters before @. */
    public const LOCAL_MIN_LENGTH = 6;

    /** Maximum characters before @ (major provider / Gmail-style cap). */
    public const LOCAL_MAX_LENGTH = 30;

    /**
     * Maximum length of the complete email address.
     * Practical PH deliverability cap (not the RFC 254 technical maximum).
     */
    public const MAX_LENGTH = 64;

    /**
     * @return list<string|ValidationRule>
     */
    public static function profilingRules(): array
    {
        return [
            'required',
            'string',
            'bail',
            'max:'.self::MAX_LENGTH,
            new self,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function profilingMessages(): array
    {
        return [
            'email.required' => self::MSG_REQUIRED,
            'email.max' => self::MSG_MAX,
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(self::MSG_FORMAT);

            return;
        }

        $email = trim($value);
        if ($email === '') {
            return;
        }

        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            $fail(self::MSG_FORMAT);

            return;
        }

        $localPart = substr($email, 0, $atPos);
        $domain = strtolower(substr($email, $atPos + 1));
        $localLen = strlen($localPart);

        if ($localLen < self::LOCAL_MIN_LENGTH) {
            $fail(self::MSG_LOCAL_MIN);

            return;
        }

        if ($localLen > self::LOCAL_MAX_LENGTH) {
            $fail(self::MSG_LOCAL_MAX);

            return;
        }

        $rfc = Validator::make(
            ['email' => $email],
            ['email' => ['email:rfc']]
        );

        if ($rfc->fails()) {
            $fail(self::MSG_FORMAT);

            return;
        }

        if ($domain === '' || ! str_contains($domain, '.')) {
            $fail(self::MSG_FORMAT);

            return;
        }

        $dns = Validator::make(
            ['email' => $email],
            ['email' => ['email:dns']]
        );

        if ($dns->fails()) {
            $fail(self::MSG_DOMAIN);
        }
    }
}
