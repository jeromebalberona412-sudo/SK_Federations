<?php

namespace App\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberService
{
    public const REGION_PH = 'PH';

    public const MSG_REQUIRED = 'Contact number is required.';

    public const MSG_INVALID = 'Please enter a valid Philippine mobile number.';

    public const MSG_FAKE = 'Please enter your actual mobile number.';

    protected PhoneNumberUtil $phoneUtil;

    public function __construct(?PhoneNumberUtil $phoneUtil = null)
    {
        $this->phoneUtil = $phoneUtil ?? PhoneNumberUtil::getInstance();
    }

    public function normalize(?string $input, string $region = self::REGION_PH): ?string
    {
        return $this->getCanonicalNumber($input, $region);
    }

    public function getCanonicalNumber(?string $input, string $region = self::REGION_PH): ?string
    {
        $raw = trim((string) $input);

        if ($raw === '') {
            return null;
        }

        try {
            $parsed = $this->phoneUtil->parse($raw, $region);

            if (! $this->phoneUtil->isValidNumber($parsed)) {
                return null;
            }

            if (! $this->isPhilippineMobileType($parsed)) {
                return null;
            }

            return $this->phoneUtil->format($parsed, PhoneNumberFormat::E164);
        } catch (NumberParseException) {
            return null;
        }
    }

    /**
     * @return array{ok: bool, canonical: ?string, error: ?string}
     */
    public function validatePhilippineMobile(
        ?string $input,
        ?int $ignoreRegistrationId = null,
        bool $checkDuplicate = false,
    ): array {
        $raw = trim((string) $input);

        if ($raw === '' || $raw === '09') {
            return ['ok' => false, 'canonical' => null, 'error' => self::MSG_REQUIRED];
        }

        // For Federation edit mode, require exactly 09XXXXXXXXX format
        $normalized = preg_replace('/\D+/', '', $raw) ?? '';

        // Must be exactly 11 digits starting with 09
        if (strlen($normalized) !== 11 || ! str_starts_with($normalized, '09')) {
            return ['ok' => false, 'canonical' => null, 'error' => 'Please enter a valid Philippine mobile number starting with 09 (09XXXXXXXXX).'];
        }

        try {
            $parsed = $this->phoneUtil->parse($raw, self::REGION_PH);
        } catch (NumberParseException) {
            return ['ok' => false, 'canonical' => null, 'error' => self::MSG_INVALID];
        }

        $national = (string) $parsed->getNationalNumber();
        $localCandidate = (strlen($national) === 10 && str_starts_with($national, '9'))
            ? '0'.$national
            : $normalized;

        // Prefer the "actual mobile" message for clear test patterns, even if
        // libphonenumber also considers the number invalid.
        if ($this->isObviouslyFake($national) || $this->isObviouslyFake($localCandidate)) {
            return ['ok' => false, 'canonical' => null, 'error' => self::MSG_FAKE];
        }

        if (! $this->phoneUtil->isValidNumber($parsed)
            || ! $this->phoneUtil->isValidNumberForRegion($parsed, self::REGION_PH)
            || ! $this->isPhilippineMobileType($parsed)) {
            return ['ok' => false, 'canonical' => null, 'error' => self::MSG_INVALID];
        }

        $canonical = $this->phoneUtil->format($parsed, PhoneNumberFormat::E164);

        // Skip duplicate check for Federation edit mode
        if ($checkDuplicate && $this->isDuplicate($canonical, $ignoreRegistrationId)) {
            return ['ok' => false, 'canonical' => null, 'error' => 'This contact number is already registered.'];
        }

        return ['ok' => true, 'canonical' => $canonical, 'error' => null];
    }

    public function isObviouslyFake(string $nationalDigits): bool
    {
        $digits = preg_replace('/\D+/', '', $nationalDigits) ?? '';

        if ($digits === '') {
            return false;
        }

        $local = $this->toLocal11($digits);
        $body = $local !== null ? substr($local, 2) : $digits;

        if ($this->isSameDigitPattern($digits) || ($local !== null && $this->isSameDigitPattern($local))) {
            return true;
        }

        if ($this->isSequential($digits) || ($local !== null && $this->isSequential(substr($local, 2)))) {
            return true;
        }

        if ($this->isRepeatedPattern($digits) || ($local !== null && $this->isRepeatedPattern($local))) {
            return true;
        }

        if ($this->hasLowDigitDiversity($body) || $this->hasExcessiveRepeatedDigit($body)) {
            return true;
        }

        if ($this->isMostlySequential($body)) {
            return true;
        }

        return false;
    }

    public function isSequential(string $digits): bool
    {
        $digits = preg_replace('/\D+/', '', $digits) ?? '';
        $length = strlen($digits);

        if ($length < 8) {
            return false;
        }

        $ascending = true;
        $descending = true;

        for ($i = 1; $i < $length; $i++) {
            $diff = (int) $digits[$i] - (int) $digits[$i - 1];
            if ($diff !== 1) {
                $ascending = false;
            }
            if ($diff !== -1) {
                $descending = false;
            }
        }

        return $ascending || $descending;
    }

    public function isRepeatedPattern(string $digits): bool
    {
        $digits = preg_replace('/\D+/', '', $digits) ?? '';
        $length = strlen($digits);

        if ($length < 8) {
            return false;
        }

        for ($blockLen = 2; $blockLen <= 4; $blockLen++) {
            if ($this->coversWithRepeatedBlock($digits, $blockLen)) {
                return true;
            }

            // Common PH mobile shape: leading 9 + repeating block (e.g. 9121212121).
            if ($length >= 9 && $this->coversWithRepeatedBlock(substr($digits, 1), $blockLen)) {
                return true;
            }

            if ($length === 11 && str_starts_with($digits, '09') && $this->coversWithRepeatedBlock(substr($digits, 2), $blockLen)) {
                return true;
            }
        }

        return false;
    }

    public function isDuplicate(string $canonical, ?int $ignoreRegistrationId = null): bool
    {
        // For Federation edit mode, we skip duplicate checking since we're editing existing records
        return false;
    }

    public function mask(?string $number): string
    {
        $value = trim((string) $number);

        if ($value === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) < 7) {
            return '****';
        }

        return substr($digits, 0, 4).'****'.substr($digits, -3);
    }

    protected function isPhilippineMobileType(object $parsed): bool
    {
        if ((int) $parsed->getCountryCode() !== 63) {
            return false;
        }

        $type = $this->phoneUtil->getNumberType($parsed);

        return in_array($type, [
            PhoneNumberType::MOBILE,
            PhoneNumberType::FIXED_LINE_OR_MOBILE,
        ], true);
    }

    protected function toLocal11(string $digits): ?string
    {
        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            return $digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return '0'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            return '0'.substr($digits, 2);
        }

        return null;
    }

    protected function hasLowDigitDiversity(string $body): bool
    {
        if (strlen($body) < 8) {
            return false;
        }

        return count(array_unique(str_split($body))) <= 2;
    }

    protected function hasExcessiveRepeatedDigit(string $body): bool
    {
        if (strlen($body) < 8) {
            return false;
        }

        // Long identical run (e.g. 000000 / 555555).
        if (preg_match('/(\d)\1{5,}/', $body) === 1) {
            return true;
        }

        $counts = array_count_values(str_split($body));
        foreach ($counts as $count) {
            // 9-digit body with one digit dominating (e.g. 09175555555).
            if ($count >= 6) {
                return true;
            }
        }

        return false;
    }

    protected function isMostlySequential(string $body): bool
    {
        $length = strlen($body);

        if ($length < 8) {
            return false;
        }

        $asc = 0;
        $desc = 0;
        $steps = $length - 1;

        for ($i = 1; $i < $length; $i++) {
            $diff = (int) $body[$i] - (int) $body[$i - 1];
            if ($diff === 1) {
                $asc++;
            }
            if ($diff === -1) {
                $desc++;
            }
        }

        // Nearly full ascending/descending walks (allow one break).
        return $asc >= ($steps - 1) || $desc >= ($steps - 1);
    }

    protected function isSameDigitPattern(string $digits): bool
    {
        // 09XXXXXXXXX where X repeats, or national 9XXXXXXXXX with same trailing digit.
        if (preg_match('/^09(\d)\1{8}$/', $digits) === 1) {
            return true;
        }

        if (preg_match('/^9(\d)\1{8}$/', $digits) === 1) {
            return true;
        }

        if (strlen($digits) >= 8 && preg_match('/^(\d)\1+$/', $digits) === 1) {
            return true;
        }

        return false;
    }

    protected function coversWithRepeatedBlock(string $digits, int $blockLen): bool
    {
        $length = strlen($digits);
        $repeats = intdiv($length, $blockLen);

        if ($repeats < 3) {
            return false;
        }

        $block = substr($digits, 0, $blockLen);

        if ($block === '' || preg_match('/^(.)\1+$/', $block) === 1) {
            return false;
        }

        $built = str_repeat($block, $repeats);

        return $built === substr($digits, 0, strlen($built))
            && strlen($built) >= (int) floor($length * 0.85);
    }
}
