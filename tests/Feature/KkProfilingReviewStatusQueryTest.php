<?php

use App\Models\KabataanRegistration;
use App\Modules\Accounts\Services\ChairpersonFederationSyncService;
use App\Modules\Accounts\Services\FederationRosterService;
use App\Modules\Dashboard\Services\DashboardStatsService;
use App\Modules\KabataanMonitoring\Support\KkProfilingReviewStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('pdo_sqlite is required for this test.');
    }

    Schema::dropIfExists('kabataan_registrations');
    Schema::create('kabataan_registrations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('barangay_id');
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('status');
        $table->string('evaluation_status')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamp('password_set_at')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    $now = now();
    DB::table('kabataan_registrations')->insert([
        ['tenant_id' => 7, 'barangay_id' => 11, 'status' => 'pending_verification', 'evaluation_status' => null, 'created_at' => $now, 'updated_at' => $now],
        ['tenant_id' => 7, 'barangay_id' => 11, 'status' => 'password_set', 'evaluation_status' => 'Wrong Credentials', 'created_at' => $now, 'updated_at' => $now],
        ['tenant_id' => 7, 'barangay_id' => 11, 'status' => 'active', 'evaluation_status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ['tenant_id' => 7, 'barangay_id' => 11, 'status' => 'rejected', 'evaluation_status' => 'Not Profiled', 'created_at' => $now, 'updated_at' => $now],
        ['tenant_id' => 7, 'barangay_id' => 11, 'status' => 'active', 'evaluation_status' => 'Duplicate', 'created_at' => $now, 'updated_at' => $now],
        ['tenant_id' => 7, 'barangay_id' => 11, 'status' => 'archived', 'evaluation_status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ['tenant_id' => 8, 'barangay_id' => 12, 'status' => 'pending_verification', 'evaluation_status' => null, 'created_at' => $now, 'updated_at' => $now],
        ['tenant_id' => 7, 'barangay_id' => 11, 'status' => 'active', 'evaluation_status' => 'active', 'deleted_at' => $now, 'created_at' => $now, 'updated_at' => $now],
    ]);
});

it('uses one tenant-scoped status scope for pending, approved, rejected, and all submissions', function () {
    $base = KabataanRegistration::query()->where('tenant_id', 7);
    $dashboardStats = new DashboardStatsService(
        Mockery::mock(ChairpersonFederationSyncService::class),
        Mockery::mock(FederationRosterService::class),
    );

    expect(KkProfilingReviewStatus::applyFilter(clone $base, 'pending')->count())->toBe(2)
        ->and(KkProfilingReviewStatus::applyFilter(clone $base, 'approved')->count())->toBe(1)
        ->and(KkProfilingReviewStatus::applyFilter(clone $base, 'rejected')->count())->toBe(3)
        ->and(KkProfilingReviewStatus::applyFilter(clone $base, 'all')->count())->toBe(6)
        ->and($dashboardStats->municipalityKkStats(7)['total_kk'])->toBe(6)
        ->and($dashboardStats->municipalityKkStats(7)['pending'])->toBe(2)
        ->and($dashboardStats->municipalityKkStats(7)['approved'])->toBe(1)
        ->and($dashboardStats->municipalityKkStats(null)['pending'])->toBe(0)
        ->and($dashboardStats->municipalityKkStats(8)['pending'])->toBe(1);
});
