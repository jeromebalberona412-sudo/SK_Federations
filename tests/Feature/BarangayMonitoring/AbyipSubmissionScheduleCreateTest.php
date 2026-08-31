<?php

use App\Models\AbyipSubmissionSchedule;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\BarangayMonitoring\Services\AbyipSubmissionScheduleService;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
