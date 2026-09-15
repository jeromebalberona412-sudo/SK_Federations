<?php

use App\Models\AbyipSubmissionSchedule;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\BarangayMonitoring\Services\AbyipSubmissionScheduleService;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

it('creates an abyip schedule when tenant_id is missing from the table', function () {
    if (! extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('pdo_sqlite is required for this test.');
    }

    Schema::dropIfExists('abyip_submission_schedule_histories');
    Schema::dropIfExists('abyip_submission_schedules');

    Schema::create('abyip_submission_schedules', function (Blueprint $table) {
        $table->id();
        $table->unsignedSmallInteger('fiscal_year');
        $table->string('title')->default('ABYIP Submission');
        $table->date('date_start');
        $table->date('deadline');
        $table->date('original_deadline');
        $table->string('status', 40)->default('upcoming');
        $table->timestamps();
    });

    $audit = Mockery::mock(AuditLogInterface::class);
    $audit->shouldReceive('log')->once();

    $user = new User;
    $user->forceFill([
        'id' => 1,
        'tenant_id' => 1,
        'name' => 'SK Federation Admin',
    ]);

    $year = (int) date('Y');
    $service = new AbyipSubmissionScheduleService($audit);
    $schedule = $service->create($user, [
        'title' => 'ABYIP Submission '.$year,
        'date_start' => now()->toDateString(),
        'deadline' => now()->endOfYear()->toDateString(),
    ]);

    expect($schedule['fiscal_year'])->toBe($year)
        ->and($schedule['title'])->toBe('ABYIP Submission '.$year)
        ->and(AbyipSubmissionSchedule::query()->count())->toBe(1)
        ->and(Schema::hasColumn('abyip_submission_schedules', 'tenant_id'))->toBeFalse();
});

it('returns a validation error instead of model-not-found when schedule id is missing', function () {
    if (! extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('pdo_sqlite is required for this test.');
    }

    Schema::dropIfExists('abyip_submission_schedule_histories');
    Schema::dropIfExists('abyip_submission_schedules');

    Schema::create('abyip_submission_schedules', function (Blueprint $table) {
        $table->id();
        $table->unsignedSmallInteger('fiscal_year');
        $table->string('title')->default('ABYIP Submission');
        $table->date('date_start');
        $table->date('deadline');
        $table->date('original_deadline');
        $table->string('status', 40)->default('upcoming');
        $table->timestamps();
    });

    $audit = Mockery::mock(AuditLogInterface::class);
    $user = new User;
    $user->forceFill([
        'id' => 1,
        'tenant_id' => 1,
        'name' => 'SK Federation Admin',
    ]);

    $service = new AbyipSubmissionScheduleService($audit);

    expect(fn () => $service->update($user, 2, [
        'title' => 'ABYIP Submission',
        'date_start' => now()->toDateString(),
        'deadline' => now()->endOfYear()->toDateString(),
        'fiscal_year' => (int) date('Y'),
    ]))->toThrow(ValidationException::class);
});

it('allows creating a schedule after a cancelled schedule for the same year', function () {
    if (! extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('pdo_sqlite is required for this test.');
    }

    Schema::dropIfExists('abyip_submission_schedule_histories');
    Schema::dropIfExists('abyip_submission_schedules');

    Schema::create('abyip_submission_schedules', function (Blueprint $table) {
        $table->id();
        $table->unsignedSmallInteger('fiscal_year');
        $table->string('title')->default('ABYIP Submission');
        $table->date('date_start');
        $table->date('deadline');
        $table->date('original_deadline');
        $table->string('status', 40)->default('upcoming');
        $table->timestamps();
    });

    $year = (int) date('Y');
    AbyipSubmissionSchedule::query()->create([
        'fiscal_year' => $year,
        'title' => 'Cancelled schedule',
        'date_start' => now()->toDateString(),
        'deadline' => now()->endOfYear()->toDateString(),
        'original_deadline' => now()->endOfYear()->toDateString(),
        'status' => AbyipSubmissionSchedule::STATUS_CANCELLED,
    ]);

    $audit = Mockery::mock(AuditLogInterface::class);
    $audit->shouldReceive('log')->once();

    $user = new User;
    $user->forceFill([
        'id' => 1,
        'tenant_id' => 1,
        'name' => 'SK Federation Admin',
    ]);

    $service = new AbyipSubmissionScheduleService($audit);

    expect($service->canCreateForCurrentYear())->toBeTrue();

    $schedule = $service->create($user, [
        'title' => 'ABYIP Submission '.$year,
        'date_start' => now()->toDateString(),
        'deadline' => now()->endOfYear()->toDateString(),
    ]);

    expect($schedule['fiscal_year'])->toBe($year)
        ->and(AbyipSubmissionSchedule::query()->count())->toBe(1)
        ->and(AbyipSubmissionSchedule::query()->first()->status)->not->toBe(AbyipSubmissionSchedule::STATUS_CANCELLED);
});
