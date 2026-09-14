<?php

use App\Modules\Shared\Models\User;
use App\Modules\Turnover\Models\FederationTurnover;
use App\Modules\Turnover\Policies\FederationTurnoverPolicy;
use App\Modules\Turnover\Services\FederationTermDetectionService;
use Illuminate\Support\Facades\Route;

it('registers remind-later and protects completion with named turnover routes', function () {
    expect(Route::has('turnover.remind-later'))->toBeTrue()
        ->and(Route::has('turnover.complete'))->toBeTrue()
        ->and(Route::has('turnover.start'))->toBeTrue()
        ->and(Route::has('logout'))->toBeTrue();

    expect(route('turnover.remind-later', [], false))->toBe('/turnover/remind-later');
});

it('complete policy requires leadership and pending confirmation status', function () {
    $termDetection = Mockery::mock(FederationTermDetectionService::class);
    $termDetection->shouldReceive('isFederationLeadershipOfficer')->andReturn(false);

    $policy = new FederationTurnoverPolicy($termDetection);
    $user = new User(['tenant_id' => 1]);
    $turnover = new FederationTurnover([
        'tenant_id' => 1,
        'status' => FederationTurnover::STATUS_PENDING_CONFIRMATION,
    ]);

    expect($policy->complete($user, $turnover))->toBeFalse();
});

it('complete policy allows leadership for pending confirmation turnover in same tenant', function () {
    $termDetection = Mockery::mock(FederationTermDetectionService::class);
    $termDetection->shouldReceive('isFederationLeadershipOfficer')->andReturn(true);

    $policy = new FederationTurnoverPolicy($termDetection);
    $user = new User(['tenant_id' => 5]);
    $turnover = new FederationTurnover([
        'tenant_id' => 5,
        'status' => FederationTurnover::STATUS_PENDING_CONFIRMATION,
    ]);

    expect($policy->complete($user, $turnover))->toBeTrue();

    $turnover->status = FederationTurnover::STATUS_PENDING_REGISTRATION;
    expect($policy->complete($user, $turnover))->toBeFalse();
});

it('global modal blade enforces mandatory copy without close control when locked', function () {
    $blade = file_get_contents(app_path('Modules/Turnover/Views/partials/global-modal.blade.php'));

    expect($blade)->toContain('SK Account Turnover Reminder')
        ->and($blade)->toContain('SK Account Turnover Required')
        ->and($blade)->toContain('Proceed to Turnover')
        ->and($blade)->toContain('Complete Account Turnover')
        ->and($blade)->toContain('turnoverLogoutBtn')
        ->and($blade)->toContain('@if($canClose)')
        ->and($blade)->toContain('data-bs-backdrop="static"');
});

it('authentication service exempts mandatory turnover leadership from ended-term login block', function () {
    $source = file_get_contents(app_path('Modules/Authentication/Services/AuthenticationService.php'));

    expect($source)->toContain('canLoginDespiteEndedTerm')
        ->and($source)->toContain('clearTurnoverReminderDismissal')
        ->and($source)->toContain('lt(now()->startOfDay())');
});
