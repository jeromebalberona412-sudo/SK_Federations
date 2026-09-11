<?php

use App\Modules\Dashboard\Services\KkProfilingChartService;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('pdo_sqlite is required for this test.');
    }

    Schema::dropIfExists('kabataan_registrations');
    Schema::dropIfExists('barangays');

    Schema::create('barangays', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('kabataan_registrations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('barangay_id');
        $table->string('status')->default('active');
        $table->string('evaluation_status')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    DB::table('barangays')->insert([
        'id' => 11,
        'tenant_id' => 7,
        'name' => 'Bubukal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Cache::flush();
});

function kkChartUser(): User
{
    $user = new User;
    $user->forceFill([
        'id' => 1,
        'tenant_id' => 7,
        'name' => 'SK Federation',
    ]);

    return $user;
}

function insertKkRegistration(array $overrides): void
{
    DB::table('kabataan_registrations')->insert(array_merge([
        'tenant_id' => 7,
        'barangay_id' => 11,
        'status' => 'active',
        'evaluation_status' => 'active',
        'submitted_at' => '2026-03-15 08:00:00',
        'reviewed_at' => '2026-03-15 08:00:00',
        'created_at' => '2026-03-15 08:00:00',
        'updated_at' => '2026-03-15 08:00:00',
        'deleted_at' => null,
    ], $overrides));
}

it('plots kk profiling months across the selected date range instead of the start year only', function () {
    insertKkRegistration([
        'evaluation_status' => 'active',
        'reviewed_at' => '2023-02-10 09:00:00',
        'submitted_at' => '2023-02-10 09:00:00',
        'created_at' => '2023-02-10 09:00:00',
    ]);
    insertKkRegistration([
        'evaluation_status' => 'ID Verified',
        'reviewed_at' => '2026-08-20 09:00:00',
        'submitted_at' => '2026-08-18 09:00:00',
        'created_at' => '2026-08-18 09:00:00',
    ]);
    insertKkRegistration([
        'evaluation_status' => 'Not Profiled',
        'status' => 'email_verified',
        'reviewed_at' => null,
        'submitted_at' => '2026-08-21 09:00:00',
        'created_at' => '2026-08-21 09:00:00',
    ]);

    $payload = app(KkProfilingChartService::class)->getChartData(kkChartUser(), new Request([
        'barangay_id' => 'all',
        'period' => 'monthly',
        'date_from' => '2023-01-01',
        'date_to' => '2026-09-11',
    ]));

    expect($payload['period'])->toBe('monthly')
        ->and($payload['labels'][0])->toBe('Jan 2023')
        ->and($payload['labels'])->toContain('Feb 2023')
        ->and($payload['labels'])->toContain('Aug 2026')
        ->and($payload['labels'])->toHaveCount(45);

    $feb2023 = array_search('Feb 2023', $payload['labels'], true);
    $aug2026 = array_search('Aug 2026', $payload['labels'], true);

    expect($payload['approved'][$feb2023])->toBe(1)
        ->and($payload['approved'][$aug2026])->toBe(1)
        ->and($payload['pending'][$aug2026])->toBe(1);
});

it('uses the selected month in the date-to year for weekly kk profiling', function () {
    insertKkRegistration([
        'evaluation_status' => 'active',
        'reviewed_at' => '2026-09-03 10:00:00',
        'submitted_at' => '2026-09-02 10:00:00',
        'created_at' => '2026-09-02 10:00:00',
    ]);
    insertKkRegistration([
        'evaluation_status' => 'active',
        'reviewed_at' => '2023-09-04 10:00:00',
        'submitted_at' => '2023-09-04 10:00:00',
        'created_at' => '2023-09-04 10:00:00',
    ]);

    $payload = app(KkProfilingChartService::class)->getChartData(kkChartUser(), new Request([
        'barangay_id' => 'all',
        'period' => 'weekly',
        'month' => 9,
        'date_from' => '2023-01-01',
        'date_to' => '2026-09-11',
    ]));

    expect($payload['period'])->toBe('weekly')
        ->and($payload['year'])->toBe(2026)
        ->and($payload['month'])->toBe(9)
        ->and(array_sum($payload['approved']))->toBe(1);
});
