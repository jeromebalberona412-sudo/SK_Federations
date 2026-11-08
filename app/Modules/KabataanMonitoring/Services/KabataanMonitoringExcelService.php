<?php

namespace App\Modules\KabataanMonitoring\Services;

use App\Models\KabataanRegistration;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KabataanMonitoringExcelService
{
    private const MAX_ROWS = 5000;

    private const MAX_COLUMNS = 23;

    private const HEADERS = [
        'REGION',
        'PROVINCE',
        'CITY/MUNICIPALITY',
        'BARANGAY',
        'PUROK/ZONE',
        'LAST NAME',
        'FIRST NAME',
        'MIDDLE NAME',
        'SUFFIX',
        'SEX ASSIGNED AT BIRTH',
        'AGE',
        'BIRTHDAY',
        'CONTACT NUMBER',
        'CIVIL STATUS',
        'YOUTH CLASSIFICATION',
        'YOUTH AGE GROUP',
        'EDUCATIONAL BACKGROUND',
        'WORK STATUS',
        'REGISTERED SK VOTER?',
        'VOTED LAST SK?',
        'ATTENDED KK ASSEMBLY?',
        'IF YES, HOW MANY TIMES?',
        'IF NO, WHY?',
    ];

    private const OPTIONS = [
        'sex' => ['Male', 'Female'],
        'suffix' => ['None', 'Jr.', 'Sr.', 'I', 'II', 'III', 'IV', 'V', 'Others'],
        'civil_status' => ['Single', 'Married', 'Widowed', 'Divorced', 'Separated', 'Annulled', 'Unknown', 'Live-in'],
        'youth_classification' => [
            'In School Youth', 'Out of School Youth', 'Working Youth', 'Youth w/ Specific Needs',
            'Person w/ Disability', 'Children in Conflict w/ Law', 'Indigenous People',
        ],
        'youth_age_group' => [
            'Child Youth (15-17 yrs old)',
            'Core Youth (18-24 yrs old)',
            'Young Adult (15-30 yrs old)',
        ],
        'education' => [
            'Elementary Level', 'Elementary Grad', 'High School Level', 'High School Grad',
            'Vocational Grad', 'College Level', 'College Grad', 'Masters Level', 'Masters Grad',
            'Doctorate Level', 'Doctorate Graduate',
        ],
        'work_status' => [
            'Employed', 'Unemployed', 'Self-Employed',
            'Currently looking for a Job', 'Not Interested Looking for a Job',
        ],
        'yes_no' => ['Yes', 'No'],
        'kk_times' => ['1-2 Times', '3-4 Times', '5 and above'],
        'kk_reason' => ['There was no KK Assembly Meeting', 'Not interested to Attend'],
    ];

    private const FIELD_HEADERS = [
        'region' => 'REGION',
        'province' => 'PROVINCE',
        'city' => 'CITY/MUNICIPALITY',
        'barangay' => 'BARANGAY',
        'purok_zone' => 'PUROK/ZONE',
        'last_name' => 'LAST NAME',
        'first_name' => 'FIRST NAME',
        'middle_name' => 'MIDDLE NAME',
        'suffix' => 'SUFFIX',
        'sex' => 'SEX ASSIGNED AT BIRTH',
        'age' => 'AGE',
        'birthday' => 'BIRTHDAY',
        'contact_number' => 'CONTACT NUMBER',
        'civil_status' => 'CIVIL STATUS',
        'youth_classification' => 'YOUTH CLASSIFICATION',
        'youth_age_group' => 'YOUTH AGE GROUP',
        'education' => 'EDUCATIONAL BACKGROUND',
        'work_status' => 'WORK STATUS',
        'sk_voter' => 'REGISTERED SK VOTER?',
        'sk_voted' => 'VOTED LAST SK?',
        'kk_assembly' => 'ATTENDED KK ASSEMBLY?',
        'kk_times' => 'IF YES, HOW MANY TIMES?',
        'kk_reason' => 'IF NO, WHY?',
    ];

    public function __construct(
        private readonly AuditLogInterface $auditLog,
        private readonly KabataanMonitoringService $monitoring,
    ) {}

    public function downloadTemplate(Barangay $barangay): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring Upload');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:W1');
        $sheet->getStyle('A1:W1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '198754']],
        ]);

        foreach (range(1, self::MAX_COLUMNS) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $slug = preg_replace('/[^a-z0-9-]+/i', '-', (string) $barangay->name);
        $filename = 'kk-monitoring-template-'.trim(strtolower((string) $slug), '-').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return array{total:int, imported:int, duplicates:int, rejected:int, errors:list<string>}
     */
    public function import(User $user, Barangay $barangay, UploadedFile $file, ?string $editedRowsJson = null): array
    {
        abort_unless($user->canAccessFederationPortal() && (int) $user->tenant_id > 0, 403);
        abort_unless((int) $barangay->tenant_id === (int) $user->tenant_id, 404);

        $rows = $this->applyPreviewEdits($this->readRows($file), $editedRowsJson);
        $zones = $this->activeZones((int) $barangay->id);
        $candidateRows = [];
        $duplicates = 0;
        $rejected = 0;
        $errors = [];

        foreach ($rows as $row) {
            $issues = $this->validateRow($row['data'], $row['number'], $barangay, $zones);
            $nameKey = $this->nameIdentityKey(
                $row['data']['last_name'] ?? '',
                $row['data']['first_name'] ?? '',
                $row['data']['middle_name'] ?? '',
                $row['data']['suffix'] ?? '',
            );

            if ($issues !== []) {
                $rejected++;
                array_push($errors, ...$issues);

                continue;
            }

            $candidateRows[] = [
                'number' => $row['number'],
                'name_key' => $nameKey,
                'data' => $row['data'],
            ];
        }

        $existingNames = [];
        if ($candidateRows !== []) {
            $existingNames = KabataanRegistration::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('barangay_id', $barangay->id)
                ->get(['last_name', 'first_name', 'middle_name', 'suffix'])
                ->mapWithKeys(fn (KabataanRegistration $record) => [
                    $this->nameIdentityKey($record->last_name, $record->first_name, $record->middle_name, $record->suffix) => true,
                ])
                ->all();
        }

        $seenNames = [];
        $validRows = [];
        foreach ($candidateRows as $candidate) {
            $nameKey = $candidate['name_key'];
            if (isset($existingNames[$nameKey]) || isset($seenNames[$nameKey])) {
                $duplicates++;
                $errors[] = "Row {$candidate['number']}: duplicate record for this barangay; it was skipped.";
                $seenNames[$nameKey] = true;

                continue;
            }

            $seenNames[$nameKey] = true;
            $validRows[] = $candidate['data'];
        }

        if ($validRows !== []) {
            $now = now();
            $payload = [];
            foreach ($validRows as $row) {
                $formData = $this->buildFormData($row, $barangay, (int) $user->id);
                $payload[] = [
                    'tenant_id' => $user->tenant_id,
                    'barangay_id' => $barangay->id,
                    'last_name' => $row['last_name'],
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'] ?: null,
                    'suffix' => strcasecmp(trim($row['suffix']), 'None') === 0 ? null : ($row['suffix'] ?: null),
                    'contact_number' => $this->normalizeMobileNumber($row['contact_number']) ?: null,
                    'form_data' => json_encode($formData),
                    'status' => 'active',
                    'evaluation_status' => 'active',
                    'submitted_at' => $now,
                    'reviewed_by_user_id' => $user->id,
                    'reviewed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::transaction(function () use ($payload): void {
                foreach (array_chunk($payload, 500) as $chunk) {
                    KabataanRegistration::query()->insert($chunk);
                }
            });
            $this->monitoring->forgetListCache((string) $barangay->name, (int) $user->tenant_id);
        }

        $this->auditLog->log('kabataan_registration.monitoring_batch_upload', $user, [
            'action' => 'monitoring_batch_upload',
            'entity_type' => 'kabataan_registration',
            'module' => 'kabataan_monitoring',
            'barangay_id' => (int) $barangay->id,
            'total' => count($rows),
            'imported' => count($validRows),
            'duplicates' => $duplicates,
            'rejected' => $rejected,
        ]);

        return [
            'total' => count($rows),
            'imported' => count($validRows),
            'duplicates' => $duplicates,
            'rejected' => $rejected,
            'errors' => array_slice($errors, 0, 100),
        ];
    }

    /**
     * Apply only row/cell edits submitted from the paginated browser preview.
     *
     * @param  list<array{number:int, data:array<string, string>}>  $rows
     * @return list<array{number:int, data:array<string, string>}>
     */
    private function applyPreviewEdits(array $rows, ?string $editedRowsJson): array
    {
        if ($editedRowsJson === null || trim($editedRowsJson) === '') {
            return $rows;
        }

        try {
            $edits = json_decode($editedRowsJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'edited_rows' => ['The edited preview data is invalid. Please select the spreadsheet again.'],
            ]);
        }

        if (! is_array($edits) || count($edits) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'edited_rows' => ['The preview can contain at most 5,000 editable rows.'],
            ]);
        }

        $rowIndexes = [];
        foreach ($rows as $index => $row) {
            $rowIndexes[$row['number']] = $index;
        }
        $allowedFields = array_keys(self::FIELD_HEADERS);
        $editedRowNumbers = [];

        foreach ($edits as $edit) {
            if (
                ! is_array($edit)
                || ! is_int($edit['number'] ?? null)
                || ! isset($rowIndexes[$edit['number']])
                || ! is_array($edit['data'] ?? null)
            ) {
                throw ValidationException::withMessages([
                    'edited_rows' => ['The edited preview contains an invalid row reference.'],
                ]);
            }
            if (isset($editedRowNumbers[$edit['number']])) {
                throw ValidationException::withMessages([
                    'edited_rows' => ['The edited preview contains a duplicate row reference.'],
                ]);
            }
            $editedRowNumbers[$edit['number']] = true;

            $rowIndex = $rowIndexes[$edit['number']];
            foreach ($edit['data'] as $field => $value) {
                if (
                    ! is_string($field)
                    || ! in_array($field, $allowedFields, true)
                    || (! is_scalar($value) && $value !== null)
                ) {
                    throw ValidationException::withMessages([
                        'edited_rows' => ['The edited preview contains an invalid cell value.'],
                    ]);
                }

                if ($field === 'birthday') {
                    $rows[$rowIndex]['data'][$field] = $this->normalizeBirthday((string) ($value ?? ''));
                } else {
                    $rows[$rowIndex]['data'][$field] = trim((string) ($value ?? ''));
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array{number:int, data:array<string, string>}>
     */
    private function readRows(UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new class(self::MAX_ROWS, self::MAX_COLUMNS) implements IReadFilter
            {
                public function __construct(
                    private readonly int $maxRows,
                    private readonly int $maxColumns,
                ) {}

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= $this->maxRows + 1
                        && Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumns;
                }
            });
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (\PhpOffice\PhpSpreadsheet\Exception|\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded file could not be read as a supported spreadsheet.'],
            ]);
        }

        $sheet = $spreadsheet->getSheet(0);
        $highestRow = min((int) $sheet->getHighestDataRow(), self::MAX_ROWS + 2);
        $headers = [];
        foreach (self::HEADERS as $expected) {
            $headers[$this->normalizeHeader($expected)] = null;
        }

        for ($column = 1; $column <= self::MAX_COLUMNS; $column++) {
            $header = $this->normalizeHeader($this->cellText($sheet->getCell([$column, 1])->getValue()));
            if ($header !== '' && array_key_exists($header, $headers)) {
                $headers[$header] = $column;
            }
        }

        $missingHeaders = [];
        foreach ($headers as $normalized => $column) {
            if ($column === null) {
                $expected = collect(self::HEADERS)->first(
                    fn (string $header) => $this->normalizeHeader($header) === $normalized
                );
                $missingHeaders[] = $expected;
            }
        }

        if ($missingHeaders !== []) {
            $spreadsheet->disconnectWorksheets();
            throw ValidationException::withMessages([
                'file' => ['Missing required column(s): '.implode(', ', $missingHeaders).'. Use the downloadable template.'],
            ]);
        }

        if ($highestRow > self::MAX_ROWS + 1) {
            $spreadsheet->disconnectWorksheets();
            throw ValidationException::withMessages([
                'file' => ['The spreadsheet exceeds the 5,000 data-row limit.'],
            ]);
        }

        $columnMap = [];
        foreach (self::FIELD_HEADERS as $field => $header) {
            $columnMap[$field] = $headers[$this->normalizeHeader($header)];
        }

        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $data = [];
            foreach ($columnMap as $field => $column) {
                $cell = $sheet->getCell([$column, $rowNumber]);
                $value = $cell->getValue();
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    $value = '';
                    $data['_formula_fields'][] = $field;
                }
                $data[$field] = $field === 'birthday'
                    ? $this->normalizeBirthday($value)
                    : $this->cellText($value);
            }

            if (trim($data['last_name']) === '' && trim($data['first_name']) === '') {
                continue;
            }

            $rows[] = ['number' => $rowNumber, 'data' => $data];
        }

        $spreadsheet->disconnectWorksheets();

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => ['No youth records were found below the header row.'],
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $zones
     * @return list<string>
     */
    private function validateRow(array $row, int $rowNumber, Barangay $barangay, array $zones): array
    {
        $errors = [];
        $label = fn (string $field): string => self::FIELD_HEADERS[$field];
        $addError = function (string $field, string $message) use (&$errors, $rowNumber, $label): void {
            $errors[] = "Row {$rowNumber}, {$label($field)}: {$message}";
        };

        if (! empty($row['_formula_fields'])) {
            foreach ($row['_formula_fields'] as $field) {
                $addError($field, 'formulas are not accepted.');
            }
        }

        foreach (['last_name', 'first_name'] as $field) {
            if (trim((string) ($row[$field] ?? '')) === '') {
                $addError($field, 'this field is required.');
            } elseif (mb_strlen($row[$field]) > 100) {
                $addError($field, 'must not exceed 100 characters.');
            }
        }

        foreach (['middle_name' => 100, 'contact_number' => 15] as $field => $max) {
            if (mb_strlen((string) ($row[$field] ?? '')) > $max) {
                $addError($field, "must not exceed {$max} characters.");
            }
        }

        $suffix = trim((string) ($row['suffix'] ?? ''));
        if ($suffix !== '' && ! $this->canonicalOption(self::OPTIONS['suffix'], $suffix)) {
            $addError('suffix', 'use a valid suffix from the template.');
        }

        foreach ([
            'sex' => 'sex',
            'civil_status' => 'civil_status',
            'youth_classification' => 'youth_classification',
            'youth_age_group' => 'youth_age_group',
            'education' => 'education',
            'work_status' => 'work_status',
        ] as $field => $optionKey) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($field === 'sex' && $value === '') {
                $addError($field, 'this field is required.');
            } elseif ($value !== '' && ! $this->canonicalOption(self::OPTIONS[$optionKey], $value)) {
                $addError($field, 'use a valid value from the template.');
            }
        }

        $age = trim((string) ($row['age'] ?? ''));
        if ($age === '' || ! preg_match('/^\d{1,2}$/', $age) || (int) $age < 15 || (int) $age > 30) {
            $addError('age', 'enter a whole-number age from 15 to 30.');
        }

        if ($row['birthday'] !== '' && $row['birthday'] === null) {
            $addError('birthday', 'enter a valid date (YYYY-MM-DD or MM/DD/YYYY).');
        }

        $contact = trim((string) ($row['contact_number'] ?? ''));
        if ($contact !== '' && $this->normalizeMobileNumber($contact) === '') {
            $addError('contact_number', 'enter a valid Philippine mobile number.');
        }

        foreach (['sk_voter', 'sk_voted', 'kk_assembly'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '' && ! $this->canonicalOption(self::OPTIONS['yes_no'], $value)) {
                $addError($field, 'choose Yes or No.');
            }
        }

        $assembly = $this->canonicalOption(self::OPTIONS['yes_no'], $row['kk_assembly'] ?? '');
        $times = trim((string) ($row['kk_times'] ?? ''));
        $reason = trim((string) ($row['kk_reason'] ?? ''));
        if ($assembly === 'Yes' && ($times === '' || ! $this->canonicalOption(self::OPTIONS['kk_times'], $times))) {
            $addError('kk_times', 'choose a valid attendance frequency when the answer is Yes.');
        } elseif ($times !== '' && ! $this->canonicalOption(self::OPTIONS['kk_times'], $times)) {
            $addError('kk_times', 'use a valid value from the template.');
        }
        if ($assembly === 'No' && ($reason === '' || ! $this->canonicalOption(self::OPTIONS['kk_reason'], $reason))) {
            $addError('kk_reason', 'choose a valid reason when the answer is No.');
        } elseif ($reason !== '' && ! $this->canonicalOption(self::OPTIONS['kk_reason'], $reason)) {
            $addError('kk_reason', 'use a valid value from the template.');
        }

        $rowBarangay = trim((string) ($row['barangay'] ?? ''));
        if ($rowBarangay !== '' && strcasecmp($rowBarangay, (string) $barangay->name) !== 0) {
            $addError('barangay', 'must match the Barangay page where this file is being uploaded.');
        }

        $purok = trim((string) ($row['purok_zone'] ?? ''));
        if ($purok === '') {
            $addError('purok_zone', 'this field is required.');
        } elseif ($zones !== [] && ! in_array(mb_strtolower($purok), array_map('mb_strtolower', $zones), true)) {
            $addError('purok_zone', 'does not match an active Purok/Zone in this barangay.');
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildFormData(array $row, Barangay $barangay, int $userId): array
    {
        $age = (int) $row['age'];
        $purok = trim($row['purok_zone']);

        return [
            'entry_source' => 'federation_monitoring',
            'monitoring_imported_at' => now()->toIso8601String(),
            'monitoring_imported_by' => $userId,
            'age' => $age,
            'birthday' => $row['birthday'] ?: null,
            'sex' => $this->canonicalOption(self::OPTIONS['sex'], $row['sex']),
            'purok_zone' => $purok,
            'civil_status' => $this->canonicalOption(self::OPTIONS['civil_status'], $row['civil_status']),
            'youth_classification' => $this->canonicalOption(self::OPTIONS['youth_classification'], $row['youth_classification']),
            'youth_age_group' => $this->ageGroup($age),
            'work_status' => $this->canonicalOption(self::OPTIONS['work_status'], $row['work_status']),
            'education' => $this->canonicalOption(self::OPTIONS['education'], $row['education']),
            'sk_voter' => $this->canonicalOption(self::OPTIONS['yes_no'], $row['sk_voter']),
            'national_voter' => null,
            'sk_voted' => $this->canonicalOption(self::OPTIONS['yes_no'], $row['sk_voted']),
            'kk_assembly' => $this->canonicalOption(self::OPTIONS['yes_no'], $row['kk_assembly']),
            'kk_times' => $this->canonicalOption(self::OPTIONS['kk_times'], $row['kk_times']),
            'kk_reason' => $this->canonicalOption(self::OPTIONS['kk_reason'], $row['kk_reason']),
            'region' => $barangay->region ?: 'Region IV-A (CALABARZON)',
            'province' => $barangay->province ?: 'Laguna',
            'city' => $barangay->municipality ?: 'Santa Cruz',
            'barangay' => $barangay->name,
        ];
    }

    /**
     * @return list<string>
     */
    private function activeZones(int $barangayId): array
    {
        if (! Schema::hasTable('barangay_zones')) {
            return [];
        }

        return DB::table('barangay_zones')
            ->where('barangay_id', $barangayId)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', trim($header)));
    }

    private function cellText(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeBirthday(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value) && (float) $value >= 20000 && (float) $value <= 80000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        foreach (['!^(\\d{4})-(\\d{1,2})-(\\d{1,2})$!', '!^(\\d{1,2})/(\\d{1,2})/(\\d{4})$!'] as $index => $pattern) {
            if (! preg_match($pattern, $value, $parts)) {
                continue;
            }
            [$year, $month, $day] = $index === 0
                ? [(int) $parts[1], (int) $parts[2], (int) $parts[3]]
                : [(int) $parts[3], (int) $parts[1], (int) $parts[2]];

            return checkdate($month, $day, $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        return null;
    }

    private function normalizeMobileNumber(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return preg_match('/^09\d{9}$/', $digits) ? $digits : '';
    }

    private function canonicalOption(array $options, ?string $value): ?string
    {
        foreach ($options as $option) {
            if (strcasecmp(trim((string) $value), $option) === 0) {
                return $option;
            }
        }

        return null;
    }

    private function ageGroup(int $age): ?string
    {
        if ($age <= 17) {
            return 'Child Youth (15-17 yrs old)';
        }
        if ($age <= 24) {
            return 'Core Youth (18-24 yrs old)';
        }
        if ($age <= 30) {
            return 'Young Adult (15-30 yrs old)';
        }

        return null;
    }

    private function nameIdentityKey(?string $last, ?string $first, ?string $middle, ?string $suffix): string
    {
        $normalize = static fn (?string $value): string => strtoupper((string) preg_replace(
            '/\s+/',
            ' ',
            trim((string) $value)
        ));
        $suffixKey = $normalize($suffix);

        return implode('|', [
            $normalize($last),
            $normalize($first),
            $normalize($middle),
            in_array($suffixKey, ['', 'NONE'], true) ? '' : $suffixKey,
        ]);
    }
}
