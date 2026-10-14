<?php

use App\Models\KabataanRegistration;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Accounts\Services\ChairpersonFederationSyncService;
use App\Modules\Accounts\Services\FederationRosterService;
use App\Modules\KabataanMonitoring\Services\KabataanMonitoringExcelService;
use App\Modules\KabataanMonitoring\Services\KabataanMonitoringService;
use App\Modules\KabataanMonitoring\Support\KkProfilingReviewStatus;
use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\HttpException;

function monitoringExcelHeaders(): array
{
    return [
        'REGION', 'PROVINCE', 'CITY/MUNICIPALITY', 'BARANGAY', 'PUROK/ZONE',
        'LAST NAME', 'FIRST NAME', 'MIDDLE NAME', 'SUFFIX', 'SEX ASSIGNED AT BIRTH',
        'AGE', 'BIRTHDAY', 'CONTACT NUMBER', 'CIVIL STATUS', 'YOUTH CLASSIFICATION',
        'YOUTH AGE GROUP', 'EDUCATIONAL BACKGROUND', 'WORK STATUS',
        'REGISTERED SK VOTER?', 'VOTED LAST SK?', 'ATTENDED KK ASSEMBLY?',
        'IF YES, HOW MANY TIMES?', 'IF NO, WHY?',
    ];
}

function monitoringExcelDataRow(array $overrides = []): array
{
    return array_replace([
        'REGION' => 'Region IV-A (CALABARZON)',
        'PROVINCE' => 'Laguna',
        'CITY/MUNICIPALITY' => 'Santa Cruz',
        'BARANGAY' => 'Barangay Test',
        'PUROK/ZONE' => 'Purok 1',
        'LAST NAME' => 'Dela Cruz',
        'FIRST NAME' => 'Juan',
        'MIDDLE NAME' => 'Santos',
        'SUFFIX' => '',
        'SEX ASSIGNED AT BIRTH' => 'Male',
        'AGE' => 20,
        'BIRTHDAY' => '2006-01-15',
        'CONTACT NUMBER' => '09171234567',
        'CIVIL STATUS' => 'Single',
        'YOUTH CLASSIFICATION' => 'In School Youth',
        'YOUTH AGE GROUP' => '',
        'EDUCATIONAL BACKGROUND' => 'High School Level',
        'WORK STATUS' => 'Unemployed',
        'REGISTERED SK VOTER?' => 'Yes',
        'VOTED LAST SK?' => 'No',
        'ATTENDED KK ASSEMBLY?' => 'Yes',
        'IF YES, HOW MANY TIMES?' => '1-2 Times',
        'IF NO, WHY?' => '',
    ], $overrides);
}

function monitoringExcelFile(array $rows): UploadedFile
{
    $sheet = (new Spreadsheet)->getActiveSheet();
    $sheet->fromArray(monitoringExcelHeaders(), null, 'A1');

    foreach (array_values($rows) as $index => $row) {
        $cells = [];
        foreach (monitoringExcelHeaders() as $header) {
            $cells[] = $row[$header] ?? '';
        }
        $sheet->fromArray($cells, null, 'A'.($index + 2));
    }

    ob_start();
    (new Xlsx($sheet->getParent()))->save('php://output');
    $contents = ob_get_clean();
    $sheet->getParent()->disconnectWorksheets();

    return UploadedFile::fake()->createWithContent('monitoring.xlsx', $contents);
}

function monitoringExcelService(): KabataanMonitoringExcelService
{
    return new KabataanMonitoringExcelService(
        Mockery::mock(AuditLogInterface::class),
        Mockery::mock(KabataanMonitoringService::class),
    );
}

function setupMonitoringImportTables(): void
{
    if (! extension_loaded('pdo_sqlite')) {
        test()->markTestSkipped('pdo_sqlite is required for persisted Federation import tests.');
    }

    Schema::dropIfExists('kabataan_registrations');
    Schema::dropIfExists('barangay_zones');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('role')->nullable();
        $table->unsignedBigInteger('tenant_id')->nullable();
        $table->boolean('has_federation_access')->default(false);
        $table->softDeletes();
        $table->timestamps();
    });
    Schema::create('barangay_zones', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('barangay_id');
        $table->string('name');
        $table->string('status')->default('active');
    });
    Schema::create('kabataan_registrations', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('barangay_id');
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
        $table->string('last_name', 100);
        $table->string('first_name', 100);
        $table->string('middle_name', 100)->nullable();
        $table->string('suffix', 10)->nullable();
        $table->string('email', 150)->nullable();
        $table->string('contact_number', 15)->nullable();
        $table->json('form_data');
        $table->string('status');
        $table->string('evaluation_status')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}

describe('Kabataan Monitoring Excel template', function () {
    it('provides the expected KK Profiling upload headers in an xlsx workbook', function () {
        $barangay = new Barangay;
        $barangay->forceFill(['id' => 12, 'tenant_id' => 3, 'name' => 'Barangay Test']);
        $response = monitoringExcelService()->downloadTemplate($barangay);

        ob_start();
        $response->sendContent();
        $contents = ob_get_clean();
        $path = tempnam(sys_get_temp_dir(), 'km-template-');
        file_put_contents($path, $contents);

        try {
            $workbook = IOFactory::load($path);
            $headers = $workbook->getSheet(0)->rangeToArray('A1:W1', null, true, false)[0];
            $workbook->disconnectWorksheets();
            expect($headers)->toBe(monitoringExcelHeaders());
        } finally {
            @unlink($path);
        }
    });
});

describe('Kabataan Monitoring Excel import', function () {
    beforeEach(function () {
        $audit = Mockery::mock(AuditLogInterface::class);
        $audit->shouldReceive('log')->zeroOrMoreTimes();
        $monitoring = Mockery::mock(KabataanMonitoringService::class);
        $monitoring->shouldReceive('forgetListCache')->zeroOrMoreTimes();
        $this->excelService = new KabataanMonitoringExcelService($audit, $monitoring);

        $this->user = Mockery::mock(User::class)->makePartial();
        $this->user->shouldReceive('canAccessFederationPortal')->andReturn(true);
        $this->user->forceFill(['id' => 7, 'role' => User::ROLE_SK_FED, 'tenant_id' => 3]);

        $this->barangay = new Barangay;
        $this->barangay->forceFill([
            'id' => 12,
            'tenant_id' => 3,
            'name' => 'Barangay Test',
            'region' => 'Region IV-A (CALABARZON)',
            'province' => 'Laguna',
            'municipality' => 'Santa Cruz',
        ]);
    });

    it('imports valid rows as approved monitoring records and skips duplicates in the same workbook', function () {
        setupMonitoringImportTables();
        $file = monitoringExcelFile([
            monitoringExcelDataRow(),
            monitoringExcelDataRow(),
        ]);

        $summary = $this->excelService->import($this->user, $this->barangay, $file);
        $record = KabataanRegistration::query()->first();

        expect($summary['imported'])->toBe(1)
            ->and($summary['duplicates'])->toBe(1)
            ->and($summary['rejected'])->toBe(0)
            ->and($record)->not->toBeNull()
            ->and($record->status)->toBe('active')
            ->and($record->evaluation_status)->toBe('active')
            ->and(KkProfilingReviewStatus::labelFor($record))->toBe('Approved')
            ->and($record->reviewed_by_user_id)->toBe(7)
            ->and($record->reviewed_at)->not->toBeNull()
            ->and($record->form_data['entry_source'])->toBe('federation_monitoring')
            ->and($record->form_data['monitoring_imported_by'])->toBe(7);

        $dashboardStats = new DashboardStatsService(
            Mockery::mock(ChairpersonFederationSyncService::class),
            Mockery::mock(FederationRosterService::class),
        );
        expect($dashboardStats->municipalityKkStats(3)['approved'])->toBe(1)
            ->and($dashboardStats->municipalityKkStats(3)['pending'])->toBe(0);
    });

    it('applies edits made in the spreadsheet preview before importing', function () {
        setupMonitoringImportTables();
        $file = monitoringExcelFile([monitoringExcelDataRow()]);

        $this->excelService->import(
            $this->user,
            $this->barangay,
            $file,
            json_encode([['number' => 2, 'data' => ['first_name' => 'Maria']]], JSON_THROW_ON_ERROR),
        );

        expect(KabataanRegistration::query()->value('first_name'))->toBe('Maria');
    });

    it('does not overwrite an existing approved profile when an imported row duplicates it', function () {
        setupMonitoringImportTables();
        KabataanRegistration::query()->create([
            'tenant_id' => 3,
            'barangay_id' => 12,
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'form_data' => ['entry_source' => 'official_import'],
            'status' => 'active',
            'evaluation_status' => 'active',
        ]);

        $summary = $this->excelService->import(
            $this->user,
            $this->barangay,
            monitoringExcelFile([monitoringExcelDataRow()])
        );
        $record = KabataanRegistration::query()->firstOrFail();

        expect($summary['imported'])->toBe(0)
            ->and($summary['duplicates'])->toBe(1)
            ->and($record->status)->toBe('active')
            ->and($record->evaluation_status)->toBe('active')
            ->and($record->form_data['entry_source'])->toBe('official_import');
    });

    it('reports row and column validation failures without writing records', function () {
        Schema::shouldReceive('hasTable')->with('barangay_zones')->andReturn(false);

        $summary = $this->excelService->import(
            $this->user,
            $this->barangay,
            monitoringExcelFile([monitoringExcelDataRow(['AGE' => 31, 'SEX ASSIGNED AT BIRTH' => 'Other'])])
        );

        expect($summary['imported'])->toBe(0)
            ->and($summary['rejected'])->toBe(1)
            ->and($summary['errors'])->toContain('Row 2, AGE: enter a whole-number age from 15 to 30.')
            ->and($summary['errors'])->toContain('Row 2, SEX ASSIGNED AT BIRTH: use a valid value from the template.');
    });

    it('rejects empty spreadsheets', function () {
        expect(fn () => $this->excelService->import(
            $this->user,
            $this->barangay,
            monitoringExcelFile([])
        ))->toThrow(ValidationException::class);
    });

    it('rejects unauthorized upload attempts before processing the file', function () {
        $unauthorized = Mockery::mock(User::class)->makePartial();
        $unauthorized->shouldReceive('canAccessFederationPortal')->andReturn(false);
        $unauthorized->forceFill(['id' => 8, 'role' => User::ROLE_USER, 'tenant_id' => 3]);

        expect(fn () => $this->excelService->import(
            $unauthorized,
            $this->barangay,
            monitoringExcelFile([monitoringExcelDataRow()])
        ))->toThrow(HttpException::class);
    });
});
