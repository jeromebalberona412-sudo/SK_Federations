<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Shared\Models\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountBatchTemplateService
{
    /**
     * @return list<string>
     */
    public function standardHeaders(): array
    {
        return [
            'First Name',
            'Middle Name (optional)',
            'Last Name',
            'Suffix (None)',
            'Sex',
            'Birthdate',
            'Age',
            'Contact Number',
            'Position',
            'Region',
            'Province',
            'Municipality',
            'Barangay',
            'Term Start Date (MM/DD/YYYY)',
            'Term End Date (MM/DD/YYYY)',
            'Email Address',
        ];
    }

    /**
     * @return list<string>
     */
    public function templateSampleRow(): array
    {
        return [
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'IV-A CALABARZON',
            'Laguna',
            'Santa Cruz',
            '',
            '',
            '',
            '',
        ];
    }

    public function normalizeHeaderLabel(string $header): string
    {
        $label = strtolower(trim($header));

        return (string) preg_replace('/\s*\([^)]*\)\s*$/', '', $label);
    }

    /**
     * @return list<string>
     */
    public function headersForRole(string $role): array
    {
        return $this->standardHeaders();
    }

    /**
     * @return list<string>
     */
    public function optionalHeaderLabels(): array
    {
        return ['Middle Name (optional)', 'Middle Name'];
    }

    /**
     * @return list<string>
     */
    public function requiredHeaderLabels(): array
    {
        return array_values(array_filter(
            $this->standardHeaders(),
            fn (string $header): bool => ! in_array($header, $this->optionalHeaderLabels(), true)
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    public function headerAliasMap(): array
    {
        return [
            'first name' => ['first name', 'first_name'],
            'middle name' => ['middle name (optional)', 'middle name', 'middle_name'],
            'last name' => ['last name', 'last_name'],
            'suffix' => ['suffix'],
            'sex' => ['sex', 'gender'],
            'birthdate' => ['birthdate', 'date of birth', 'birth date', 'date_of_birth', 'dob'],
            'age' => ['age'],
            'contact number' => ['contact number', 'contact_number', 'contact'],
            'position' => ['position'],
            'region' => ['region'],
            'province' => ['province'],
            'municipality' => ['municipality'],
            'barangay' => ['barangay', 'barangay name', 'barangay_name'],
            'term start date' => [
                'term start date (mm/dd/yyyy)',
                'term start date',
                'term start',
                'term_start',
                'start date',
            ],
            'term end date' => [
                'term end date (mm/dd/yyyy)',
                'term end date',
                'term end',
                'term_end',
                'end date',
            ],
            'email address' => ['email address', 'email'],
        ];
    }

    /**
     * @param  list<string>  $uploadedHeaders
     * @return list<string>
     */
    public function missingRequiredHeaders(array $uploadedHeaders): array
    {
        $normalized = array_map(
            fn (mixed $header): string => $this->normalizeHeaderLabel((string) $header),
            $uploadedHeaders
        );

        $missing = [];

        foreach ($this->requiredHeaderLabels() as $label) {
            $canonical = $this->normalizeHeaderLabel($label);
            $aliases = $this->headerAliasMap()[$canonical] ?? [$canonical];

            $found = false;
            foreach ($aliases as $alias) {
                if (in_array($alias, $normalized, true)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function downloadResponse(string $role): StreamedResponse
    {
        $filename = $role === User::ROLE_SK_FED
            ? 'sk-federation-batch-template.csv'
            : 'sk-officials-batch-template.csv';

        $headers = $this->headersForRole($role);

        return response()->streamDownload(function () use ($headers): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);
            fputcsv($handle, $this->templateSampleRow());
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadXlsxResponse(string $role): BinaryFileResponse|StreamedResponse
    {
        $filename = $role === User::ROLE_SK_FED
            ? 'sk-federation-batch-template.xlsx'
            : 'sk-officials-batch-template.xlsx';

        $path = __DIR__.'/../assets/templates/'.$filename;

        $this->writeXlsxTemplate($path, $this->headersForRole($role), $this->templateSampleRow());

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $sampleRow
     */
    private function writeXlsxTemplate(string $path, array $headers, array $sampleRow = []): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to generate batch template file.');
        }

        $rows = [$headers];
        if ($sampleRow !== []) {
            $rows[] = $sampleRow;
        }

        $sharedStrings = '';
        $sharedIndex = 0;
        $indexMap = [];
        $sheetRows = '';

        foreach ($rows as $rowNumber => $rowValues) {
            $sheetCells = '';
            foreach ($headers as $columnIndex => $header) {
                $value = (string) ($rowValues[$columnIndex] ?? '');
                if (! array_key_exists($value, $indexMap)) {
                    $indexMap[$value] = $sharedIndex;
                    $sharedStrings .= '<si><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></si>';
                    $sharedIndex++;
                }
                $sheetCells .= '<c r="'.$this->columnLetter($columnIndex).($rowNumber + 1).'" t="s"><v>'.$indexMap[$value].'</v></c>';
            }
            $sheetRows .= '<row r="'.($rowNumber + 1).'">'.$sheetCells.'</row>';
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Template" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$sharedIndex.'" uniqueCount="'.$sharedIndex.'">'
            .$sharedStrings.'</sst>');
        $zip->close();
    }

    private function columnLetter(int $index): string
    {
        $index += 1;
        $letters = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }
}
