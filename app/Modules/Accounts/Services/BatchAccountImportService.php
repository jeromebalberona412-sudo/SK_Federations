<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Support\SkOfficialTermDates;
use App\Modules\Shared\Models\User;
use App\Rules\ValidEmailAddress;
use App\Services\PhoneNumberService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BatchAccountImportService
{
    /** @var array<string, int> */
    private array $barangayLookup = [];

    private ?int $defaultBarangayId = null;

    public function __construct(private readonly int $tenantId)
    {
        $this->barangayLookup = [];

        Barangay::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->each(function (Barangay $barangay): void {
                if ($this->defaultBarangayId === null) {
                    $this->defaultBarangayId = $barangay->id;
                }

                $this->registerBarangayAlias($barangay->name, $barangay->id);
            });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{row: int, error: string}>
     */
    public function validateRows(array $rows, string $role): array
    {
        $errors = [];
        $seenEmails = [];
        $seenRows = [];
        $seenFullNames = [];
        /** @var array<int, array{total: int, chairperson: int, secretary: int, treasurer: int, kagawad: int}> $rosterInFileByBarangay */
        $rosterInFileByBarangay = [];
        $rosterLimitsService = app(SkOfficialRosterLimitsService::class);

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors[] = ['row' => $index + 1, 'error' => 'Invalid row format.'];

                continue;
            }

            try {
                $normalized = $this->normalizeAccountRow($row, $role, true);
                $email = $normalized['email'];

                if (isset($seenEmails[$email])) {
                    $errors[] = ['row' => $index + 1, 'error' => 'Duplicate email in upload file.'];

                    continue;
                }

                $seenEmails[$email] = true;

                $fullNameKey = $this->fullNameKey($normalized);
                if ($fullNameKey !== '' && isset($seenFullNames[$fullNameKey])) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => 'This full name already appears in another row (same First Name, Middle Name, Last Name, and Suffix). Please change the name.',
                    ];
                } elseif ($fullNameKey !== '') {
                    $seenFullNames[$fullNameKey] = $index + 1;
                }

                $fingerprint = $this->rowFingerprint(array_merge($normalized, [
                    'barangay' => mb_strtoupper($this->stringValue($row, ['barangay', 'barangay_name', 'barangay name']), 'UTF-8'),
                ]));
                if (isset($seenRows[$fingerprint])) {
                    $errors[] = [
                        'row' => $index + 1,
                        'error' => 'Duplicate row: another entry has the same details.',
                    ];

                    continue;
                }

                $seenRows[$fingerprint] = $index + 1;

                if (User::query()->where('email', $email)->whereNull('deleted_at')->exists()) {
                    $errors[] = ['row' => $index + 1, 'error' => 'Email is already registered.'];
                }

                if ($role === User::ROLE_SK_OFFICIAL) {
                    $barangayId = (int) ($normalized['barangay_id'] ?? 0);
                    $position = (string) ($normalized['position'] ?? '');

                    if ($barangayId > 0 && $position !== '') {
                        $pending = $rosterInFileByBarangay[$barangayId] ?? [
                            'total' => 0,
                            'chairperson' => 0,
                            'secretary' => 0,
                            'treasurer' => 0,
                            'kagawad' => 0,
                        ];

                        try {
                            $rosterLimitsService->assertRosterLimits(
                                $this->tenantId,
                                $barangayId,
                                $position,
                                null,
                                isset($normalized['term_start']) ? (string) $normalized['term_start'] : null,
                                isset($normalized['term_end']) ? (string) $normalized['term_end'] : null,
                                $pending,
                            );

                            $kind = $rosterLimitsService->positionKind($position);
                            $pending['total']++;
                            if (isset($pending[$kind])) {
                                $pending[$kind]++;
                            }
                            $rosterInFileByBarangay[$barangayId] = $pending;
                        } catch (ValidationException $rosterException) {
                            $errors[] = [
                                'row' => $index + 1,
                                'error' => collect($rosterException->errors())->flatten()->first()
                                    ?? 'This barangay roster limit was reached (max 10: 1 Chairperson, 1 Secretary, 1 Treasurer, 7 Kagawad).',
                            ];
                        }
                    }
                }
            } catch (ValidationException $exception) {
                $errors[] = [
                    'row' => $index + 1,
                    'error' => collect($exception->errors())->flatten()->first() ?? 'Validation failed.',
                ];
            }
        }

        return $errors;
    }

    private function registerBarangayAlias(string $name, int $id): void
    {
        $this->barangayLookup[$this->normalizeKey($name)] = $id;

        $canonical = $this->normalizeBarangayName($name);
        if ($canonical !== $name) {
            $this->barangayLookup[$this->normalizeKey($canonical)] = $id;
        }

        if (preg_match('/^poblacion\s+([ivx]+)$/i', $canonical, $matches)) {
            $roman = strtoupper($matches[1]);
            $digit = $this->romanToDigit($roman);

            if ($digit !== null) {
                $this->barangayLookup[$this->normalizeKey('Poblacion '.$digit)] = $id;
                $this->barangayLookup[$this->normalizeKey('Poblacion '.$roman)] = $id;
                $this->barangayLookup[$this->normalizeKey('Barangay '.$digit)] = $id;
                $this->barangayLookup[$this->normalizeKey('Barangay '.$roman)] = $id;
                $this->barangayLookup[$this->normalizeKey('Barangay '.$roman.' (Poblacion)')] = $id;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function normalizeAccountRow(array $row, string $role, bool $strictDemographics = true): array
    {
        $firstName = mb_strtoupper($this->stringValue($row, ['first_name', 'first name']), 'UTF-8');
        $lastName = mb_strtoupper($this->stringValue($row, ['last_name', 'last name']), 'UTF-8');
        $positionRaw = mb_strtoupper($this->stringValue($row, ['position']), 'UTF-8');
        $region = trim($this->stringValue($row, ['region']) ?: 'IV-A CALABARZON');
        $province = trim($this->stringValue($row, ['province']) ?: 'Laguna');
        $municipality = trim($this->stringValue($row, ['municipality']) ?: 'Santa Cruz');
        $barangayName = mb_strtoupper($this->stringValue($row, ['barangay', 'barangay_name', 'barangay name']), 'UTF-8');

        if ($role === User::ROLE_SK_OFFICIAL || $role === User::ROLE_SK_FED) {
            $firstName = preg_replace('/\s+/u', ' ', trim($firstName)) ?? $firstName;
            $lastName = preg_replace('/\s+/u', ' ', trim($lastName)) ?? $lastName;
        }
        $email = strtolower($this->stringValue($row, ['email', 'email address']));
        $barangayId = $this->resolveBarangayId(
            $this->intValue($row, ['barangay_id', 'barangay id']) ?: null,
            $barangayName !== '' ? $barangayName : $municipality
        );

        if ($barangayId === null && $role === User::ROLE_SK_FED && ! $strictDemographics) {
            $barangayId = $this->defaultBarangayId;
        }

        $status = User::STATUS_ACTIVE;
        $position = $this->normalizePosition($positionRaw, $role);

        $termEnd = $this->parseDate($this->rawValue($row, [
            'term_end', 'term end date', 'term end', 'term_end_date', 'end date', 'term expiry', 'term expiry date',
        ]));
        $termStart = $this->parseDate($this->rawValue($row, [
            'term_start', 'term start date', 'term start', 'term_start_date', 'start date', 'term begin', 'term begin date',
        ]));

        $leadershipDefaults = SkOfficialTermDates::leadershipDefaults($this->tenantId);
        $presidentTerms = SkOfficialTermDates::presidentTermDates($this->tenantId);

        // President term is the source of truth for all SK Official batch rows.
        if ($role === User::ROLE_SK_OFFICIAL && $presidentTerms !== null) {
            $termStart = $presidentTerms['term_start'];
            $termEnd = $presidentTerms['term_end'];
        } else {
            if ($termStart === null) {
                $termStart = $leadershipDefaults['term_start'];
            }

            if ($termEnd === null) {
                $termEnd = ($termStart === $leadershipDefaults['term_start'])
                    ? $leadershipDefaults['term_end']
                    : SkOfficialTermDates::termEndForStart($termStart)->toDateString();
            }
        }

        $dateOfBirth = $this->parseDate($this->rawValue($row, [
            'date_of_birth', 'birthdate', 'date of birth', 'birth date', 'dob',
        ]));

        $suffixRaw = $this->stringValue($row, ['suffix', 'suffix_input', 'suffix input']);
        // Empty suffix defaults to None (no suffix).
        if (trim($suffixRaw) === '') {
            $suffixRaw = 'None';
        }
        $suffix = $this->normalizeSuffix($suffixRaw);
        $rawSex = $this->stringValue($row, ['sex']);
        $sex = $this->normalizeSex($rawSex);
        $contactNumber = $this->normalizeContactNumber($this->stringValue($row, ['contact_number', 'contact number']));

        $middleNameRaw = $this->stringValue($row, ['middle_name', 'middle name']);
        $middleName = $middleNameRaw !== '' ? mb_strtoupper($middleNameRaw, 'UTF-8') : null;
        if ($middleName !== null) {
            $middleName = preg_replace('/\s+/u', ' ', trim($middleName)) ?? $middleName;
            $middleName = $middleName !== '' ? $middleName : null;
        }

        $age = $this->intValue($row, ['age']);
        if ($dateOfBirth !== null) {
            try {
                $age = Carbon::parse($dateOfBirth)->age;
            } catch (\Throwable) {
                // Keep parsed age from the sheet when DOB cannot be computed.
            }
        }

        $data = [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'suffix_input' => $suffixRaw,
            'sex_input' => $rawSex,
            'sex' => $sex,
            'date_of_birth' => $dateOfBirth,
            'age' => $age,
            'contact_number' => $contactNumber,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'barangay_id' => $barangayId,
            'barangay_name' => $barangayName,
            'position' => $position,
            'region' => $region,
            'province' => $province,
            'municipality' => $municipality,
            'term_start' => $termStart,
            'term_end' => $termEnd,
            'term_status' => 'ACTIVE',
        ];

        $this->assertRowIsValid($data, $barangayName, $role, $strictDemographics);

        unset($data['suffix_input'], $data['barangay_name']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertRowIsValid(array $data, string $barangayName, string $role, bool $strictDemographics): void
    {
        $errors = [];
        $isOfficial = $role === User::ROLE_SK_OFFICIAL;
        $namePattern = '/^(?!\s)[\p{L}.\-\']+(?: [\p{L}.\-\']+)*$/u';
        $nameMax = $isOfficial ? 150 : 35;
        $nameMin = $isOfficial ? 2 : 3;

        if ($data['first_name'] === '') {
            $errors[] = 'First name is required.';
        } elseif (mb_strlen($data['first_name']) < $nameMin) {
            $errors[] = 'First name must be at least '.$nameMin.' characters.';
        } elseif (mb_strlen($data['first_name']) > $nameMax) {
            $errors[] = 'First name must not exceed '.$nameMax.' characters.';
        } elseif (! preg_match($namePattern, $data['first_name'])) {
            $errors[] = 'First name must use uppercase letters only; need a letter before each space; no pure spaces.';
        }

        if ($data['middle_name'] !== null && mb_strlen((string) $data['middle_name']) > 0 && mb_strlen((string) $data['middle_name']) < $nameMin) {
            $errors[] = 'Middle name must be at least '.$nameMin.' characters when provided.';
        } elseif ($data['middle_name'] !== null && mb_strlen((string) $data['middle_name']) > $nameMax) {
            $errors[] = 'Middle name must not exceed '.$nameMax.' characters.';
        } elseif ($data['middle_name'] !== null && ! preg_match($namePattern, (string) $data['middle_name'])) {
            $errors[] = 'Middle name must use uppercase letters only; need a letter before each space; no pure spaces.';
        }

        if ($data['last_name'] === '') {
            $errors[] = 'Last name is required.';
        } elseif (mb_strlen($data['last_name']) < $nameMin) {
            $errors[] = 'Last name must be at least '.$nameMin.' characters.';
        } elseif (mb_strlen($data['last_name']) > $nameMax) {
            $errors[] = 'Last name must not exceed '.$nameMax.' characters.';
        } elseif (! preg_match($namePattern, $data['last_name'])) {
            $errors[] = 'Last name must use uppercase letters only; need a letter before each space; no pure spaces.';
        }

        if ($data['suffix'] !== null && mb_strlen((string) $data['suffix']) > 5) {
            $errors[] = 'Suffix must not exceed 5 characters.';
        }

        $emailValidator = Validator::make(
            ['email' => $data['email']],
            ['email' => ValidEmailAddress::profilingRules()],
            ValidEmailAddress::profilingMessages()
        );
        if ($emailValidator->fails()) {
            $errors[] = (string) $emailValidator->errors()->first('email');
        }

        if ($strictDemographics && trim((string) ($data['region'] ?? '')) !== 'IV-A CALABARZON') {
            $errors[] = 'Region must be IV-A CALABARZON.';
        }

        if ($strictDemographics && trim((string) ($data['province'] ?? '')) !== 'Laguna') {
            $errors[] = 'Province must be Laguna.';
        }

        if ($strictDemographics && trim((string) ($data['municipality'] ?? '')) !== 'Santa Cruz') {
            $errors[] = 'Municipality must be Santa Cruz.';
        }

        if ($data['position'] === null || $data['position'] === '') {
            $errors[] = 'Position is required or not recognized for this account type.';
        }

        if ($data['barangay_id'] === null) {
            $errors[] = $barangayName !== ''
                ? 'Barangay "'.$barangayName.'" was not found.'
                : 'Barangay is required.';
        }

        if ($strictDemographics) {
            if ($data['sex'] === null) {
                $errors[] = 'Sex must be exactly Male or Female.';
            }

            if ($data['date_of_birth'] === null) {
                $errors[] = 'Birthdate is required.';
            } else {
                $dob = Carbon::parse($data['date_of_birth'])->startOfDay();
                if ($dob->isFuture() || $dob->greaterThanOrEqualTo(Carbon::today())) {
                    $errors[] = 'Birthdate must be before today.';
                } else {
                    // Age eligibility is as of today (not term_start), so 18+ today always passes.
                    $birthdateError = match ($role) {
                        User::ROLE_SK_FED => SkOfficialTermDates::federationBirthdateErrorFor($dob, null),
                        User::ROLE_SK_OFFICIAL => SkOfficialTermDates::officialAccountBirthdateErrorFor($dob, null),
                        default => SkOfficialTermDates::birthdateErrorFor($dob, null),
                    };
                    if ($birthdateError !== null) {
                        $errors[] = $birthdateError;
                    }
                }
            }

            // Eligibility comes from the birthdate above; age is only sanity-checked.
            $currentAge = isset($dob) ? $dob->age : ($data['age'] !== null ? (int) $data['age'] : null);
            if ($currentAge === null || $currentAge < 1 || $currentAge > 150) {
                $errors[] = 'Age must be a valid number.';
            }

            if ($data['contact_number'] === '') {
                $errors[] = PhoneNumberService::MSG_REQUIRED;
            } else {
                $phoneResult = app(PhoneNumberService::class)->validatePhilippineMobile(
                    $data['contact_number'],
                    null,
                    false,
                );
                if (! $phoneResult['ok']) {
                    $errors[] = $phoneResult['error'] ?? PhoneNumberService::MSG_INVALID;
                }
            }

            if ($data['term_start'] === null) {
                $errors[] = 'Term start date is required.';
            }

            if ($data['term_end'] === null) {
                $errors[] = 'Term end date is required.';
            }
        }

        if ($data['term_start'] !== null && $data['term_end'] !== null) {
            $leadership = SkOfficialTermDates::leadershipDefaults($this->tenantId);
            $errors = array_merge($errors, array_values(SkOfficialTermDates::errorsFor(
                $data['term_start'],
                $data['term_end'],
                null,
                $leadership
            )));
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'row' => implode(' ', $errors),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fullNameKey(array $data): string
    {
        $first = mb_strtolower(trim((string) ($data['first_name'] ?? '')), 'UTF-8');
        $middle = mb_strtolower(trim((string) ($data['middle_name'] ?? '')), 'UTF-8');
        $last = mb_strtolower(trim((string) ($data['last_name'] ?? '')), 'UTF-8');
        if ($first === '' || $last === '') {
            return '';
        }

        $suffix = mb_strtoupper(trim((string) ($data['suffix'] ?? '')), 'UTF-8');
        if ($suffix === '' || in_array($suffix, ['NONE', 'N/A', 'NA', '-'], true)) {
            $suffix = 'NONE';
        }

        return mb_strtolower(implode('|', [$first, $middle, $last, $suffix]), 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rowFingerprint(array $data): string
    {
        $parts = [
            $data['first_name'] ?? '',
            $data['middle_name'] ?? '',
            $data['last_name'] ?? '',
            $data['suffix'] ?? '',
            $data['sex'] ?? '',
            $data['date_of_birth'] ?? '',
            (string) ($data['age'] ?? ''),
            $data['contact_number'] ?? '',
            $data['email'] ?? '',
            $data['position'] ?? '',
            $data['region'] ?? '',
            $data['province'] ?? '',
            $data['municipality'] ?? '',
            $data['barangay'] ?? $data['barangay_name'] ?? '',
            $data['term_start'] ?? '',
            $data['term_end'] ?? '',
        ];

        return mb_strtolower(implode('|', array_map(
            fn (mixed $value): string => trim((string) $value),
            $parts
        )), 'UTF-8');
    }

    private function normalizeContactNumber(string $value): string
    {
        $phone = app(PhoneNumberService::class);
        $local = $phone->toLocalMobile($value);
        if ($local !== null) {
            return $local;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '63') && strlen($digits) >= 12) {
            $digits = '0'.substr($digits, 2);
        }

        if (! str_starts_with($digits, '09')) {
            $digits = '09'.ltrim($digits, '0');
        }

        return substr($digits, 0, 11);
    }

    private function resolveBarangayId(?int $barangayId, string $barangayName): ?int
    {
        if ($barangayId !== null && $barangayId > 0) {
            $exists = Barangay::query()
                ->where('id', $barangayId)
                ->where('tenant_id', $this->tenantId)
                ->exists();

            return $exists ? $barangayId : null;
        }

        if ($barangayName === '') {
            return null;
        }

        $canonicalName = $this->normalizeBarangayName($barangayName);

        return $this->barangayLookup[$this->normalizeKey($canonicalName)]
            ?? $this->barangayLookup[$this->normalizeKey($barangayName)]
            ?? null;
    }

    private function normalizeBarangayName(string $name): string
    {
        $trimmed = trim($name);

        if (preg_match('/^barangay\s+([0-9ivx]+)\s*\(poblacion\)$/i', $trimmed, $matches)) {
            return 'Poblacion '.$this->normalizeRomanOrDigitToken($matches[1]);
        }

        if (preg_match('/^poblacion\s+([0-9ivx]+)$/i', $trimmed, $matches)) {
            return 'Poblacion '.$this->normalizeRomanOrDigitToken($matches[1]);
        }

        if (preg_match('/^barangay\s+([0-9ivx]+)$/i', $trimmed, $matches)) {
            return 'Poblacion '.$this->normalizeRomanOrDigitToken($matches[1]);
        }

        if (Str::lower($trimmed) === 'santa cruz') {
            return $trimmed;
        }

        return $trimmed;
    }

    private function normalizeRomanOrDigitToken(string $token): string
    {
        $token = trim($token);

        if (ctype_digit($token)) {
            return $this->digitToRoman((int) $token) ?? $token;
        }

        return strtoupper($token);
    }

    private function digitToRoman(int $digit): ?string
    {
        return match ($digit) {
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            default => null,
        };
    }

    private function romanToDigit(string $roman): ?int
    {
        return match (strtoupper($roman)) {
            'I' => 1,
            'II' => 2,
            'III' => 3,
            'IV' => 4,
            'V' => 5,
            default => null,
        };
    }

    private function normalizePosition(string $value, string $role): ?string
    {
        $normalized = Str::lower(trim($value));
        $normalized = str_replace(['sk ', 'sangguniang kabataan '], '', $normalized);

        $aliases = [
            'chairperson' => 'Chairperson',
            'chairman' => 'Chairperson',
            'secretary' => 'Secretary',
            'treasurer' => 'Treasurer',
            'kagawad' => 'Kagawad',
            'sk chairperson' => 'Chairperson',
            'sk secretary' => 'Secretary',
            'sk treasurer' => 'Treasurer',
            'sk kagawad' => 'Kagawad',
            'councilor' => 'Kagawad',
            'sk councilor' => 'Kagawad',
            'auditor' => 'Auditor',
            'pio' => 'PIO',
            'president' => 'President',
            'vice president' => 'Vice President',
            'sergeant at arms' => 'Sergeant at Arms',
        ];

        $position = $aliases[$normalized] ?? null;

        if ($position === null && $value !== '') {
            $allowed = OfficialProfile::positionsForRole($role);
            foreach ($allowed as $allowedPosition) {
                if (Str::lower($allowedPosition) === $normalized) {
                    $position = $allowedPosition;
                    break;
                }
            }
        }

        if ($position === null) {
            return null;
        }

        $allowed = OfficialProfile::positionsForRole($role);

        return in_array($position, $allowed, true) ? $position : null;
    }

    private function normalizeSuffix(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = trim($value);

        if (in_array(Str::lower($normalized), ['none', 'n/a', 'na', '-'], true)) {
            return null;
        }

        return match (Str::lower(rtrim($normalized, '.'))) {
            'jr' => 'Jr.',
            'sr' => 'Sr.',
            'ii' => 'II',
            'iii' => 'III',
            'iv' => 'IV',
            'v' => 'V',
            default => mb_strlen($normalized) <= 5 ? mb_strtoupper($normalized, 'UTF-8') : null,
        };
    }

    private function normalizeSex(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim($value);
        $lower = Str::lower($trimmed);

        return match ($lower) {
            'male', 'm' => 'Male',
            'female', 'f' => 'Female',
            default => in_array($trimmed, ['Male', 'Female'], true) ? $trimmed : null,
        };
    }

    private function parseDate(mixed $value): ?string
    {
        $value = $this->unwrapCellValue($value);

        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            // Excel date cells are calendar dates (UTC midnight). Use UTC Y-m-d
            // so timezone conversion does not shift the day in preview/import.
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))
                ->utc()
                ->toDateString();
        }

        if (is_numeric($value) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $value))) {
            $serial = (float) $value;
            if ($serial >= 1 && $serial < 1000000) {
                return Carbon::createFromDate(1899, 12, 30)->addDays((int) round($serial))->toDateString();
            }
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '' || preg_match('/^m{1,2}\/d{1,2}\/y{2,4}$/i', $stringValue) === 1) {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'm/d/y', 'n/j/y', 'Y-m-d H:i:s', 'm-d-Y', 'n-j-Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $stringValue);

                if ($parsed !== false) {
                    return $parsed->toDateString();
                }
            } catch (\Throwable) {
                // Try the next common spreadsheet format.
            }
        }

        try {
            return Carbon::parse($stringValue)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function unwrapCellValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (isset($value['text']) && is_string($value['text'])) {
                return $value['text'];
            }
            if (isset($value['v'])) {
                return $value['v'];
            }
            if (isset($value['hyperlink']) && is_string($value['hyperlink'])) {
                return $value['hyperlink'];
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function rawValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }

            $value = $this->unwrapCellValue($row[$key]);
            if ($value instanceof \DateTimeInterface) {
                return $value;
            }
            if (is_numeric($value) && trim((string) $value) !== '') {
                return $value;
            }
            if (trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function stringValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $this->unwrapCellValue($row[$key]);
            if ($value instanceof \DateTimeInterface) {
                $value = Carbon::instance(\DateTimeImmutable::createFromInterface($value))
                    ->utc()
                    ->toDateString();
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function intValue(array $row, array $keys): ?int
    {
        $raw = $this->rawValue($row, $keys);
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return (int) round((float) $raw);
        }

        $value = trim((string) $raw);
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function normalizeKey(string $value): string
    {
        return Str::lower(trim($value));
    }
}
