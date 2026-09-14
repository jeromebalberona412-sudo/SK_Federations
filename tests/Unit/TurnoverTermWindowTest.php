<?php

use App\Modules\Accounts\Services\FederationRosterService;
use App\Modules\Shared\Models\User;
use App\Modules\Turnover\Middleware\EnsureTurnoverTermLock;
use App\Modules\Turnover\Services\FederationTermDetectionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    Carbon::setTestNow();
    Mockery::close();
    session()->forget('turnover_reminder_dismissed');
});

function makeTermDetectionService(): FederationTermDetectionService
{
    return new FederationTermDetectionService(
        Mockery::mock(FederationRosterService::class)
    );
}

it('treats notice window as exactly seven days before term end through the end date', function () {
    $service = makeTermDetectionService();
    $termEnd = Carbon::parse('2026-07-06', config('app.timezone'));

    Carbon::setTestNow(Carbon::parse('2026-06-28', config('app.timezone')));
    expect($service->isWithinNoticeWindow($termEnd))->toBeFalse()
        ->and($service->isTermEnded($termEnd))->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-06-29', config('app.timezone')));
    expect($service->isWithinNoticeWindow($termEnd))->toBeTrue()
        ->and($service->isTermEnded($termEnd))->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-07-03', config('app.timezone')));
    expect($service->isWithinNoticeWindow($termEnd))->toBeTrue()
        ->and($service->isTermEnded($termEnd))->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-07-06 23:59:59', config('app.timezone')));
    expect($service->isWithinNoticeWindow($termEnd))->toBeTrue()
        ->and($service->isTermEnded($termEnd))->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-07-07 00:00:00', config('app.timezone')));
    expect($service->isWithinNoticeWindow($termEnd))->toBeFalse()
        ->and($service->isTermEnded($termEnd))->toBeTrue();
});

it('does not show reminder modal for non-leadership users', function () {
    $service = Mockery::mock(FederationTermDetectionService::class)->makePartial();
    $service->shouldReceive('isFederationLeadershipOfficer')->andReturn(false);

    $user = new User(['tenant_id' => 1]);

    expect($service->shouldShowTurnoverModal($user))->toBeFalse()
        ->and($service->mustLockPortalForTurnover($user))->toBeFalse();
});

it('hides dismissible reminder after session dismiss but still locks when term ended', function () {
    $service = Mockery::mock(FederationTermDetectionService::class)->makePartial();
    $service->shouldReceive('isFederationLeadershipOfficer')->andReturn(true);
    $service->shouldReceive('currentLeadershipContext')->andReturn([
        'president' => null,
        'vice_president' => null,
        'term_end' => Carbon::parse('2026-07-06'),
        'current_term_id' => 10,
    ]);
    $service->shouldReceive('activeTurnoverForTenant')->andReturn(null);
    $service->shouldReceive('hasCompletedTurnoverForTerm')->andReturn(false);

    $user = new User(['tenant_id' => 1]);

    Carbon::setTestNow(Carbon::parse('2026-07-01'));
    session()->put('turnover_reminder_dismissed', true);
    expect($service->shouldShowTurnoverModal($user))->toBeFalse()
        ->and($service->mustLockPortalForTurnover($user))->toBeFalse();

    session()->forget('turnover_reminder_dismissed');
    Carbon::setTestNow(Carbon::parse('2026-07-08'));
    expect($service->shouldShowTurnoverModal($user))->toBeTrue()
        ->and($service->mustLockPortalForTurnover($user))->toBeTrue()
        ->and($service->canLoginDespiteEndedTerm($user))->toBeTrue();
});

it('does not lock portal after turnover is completed for the term', function () {
    $service = Mockery::mock(FederationTermDetectionService::class)->makePartial();
    $service->shouldReceive('isFederationLeadershipOfficer')->andReturn(true);
    $service->shouldReceive('currentLeadershipContext')->andReturn([
        'president' => null,
        'vice_president' => null,
        'term_end' => Carbon::parse('2026-07-06'),
        'current_term_id' => 10,
    ]);
    $service->shouldReceive('activeTurnoverForTenant')->andReturn(null);
    $service->shouldReceive('hasCompletedTurnoverForTerm')->andReturn(true);

    $user = new User(['tenant_id' => 1]);
    Carbon::setTestNow(Carbon::parse('2026-07-08'));

    expect($service->mustLockPortalForTurnover($user))->toBeFalse()
        ->and($service->shouldShowTurnoverModal($user))->toBeFalse()
        ->and($service->canLoginDespiteEndedTerm($user))->toBeFalse();
});

it('redirects blocked routes during mandatory turnover while allowing logout and dashboard', function () {
    $termDetection = Mockery::mock(FederationTermDetectionService::class);
    $termDetection->shouldReceive('mustLockPortalForTurnover')->andReturn(true);

    $middleware = new EnsureTurnoverTermLock($termDetection);
    $user = new User(['id' => 1, 'tenant_id' => 1]);

    $blocked = Request::create('/community-feed', 'GET');
    $blocked->setUserResolver(fn () => $user);

    $blockedResponse = $middleware->handle($blocked, fn () => response('ok'));
    expect($blockedResponse->isRedirect())->toBeTrue();

    $allowed = Request::create('/dashboard', 'GET');
    $allowed->setUserResolver(fn () => $user);

    $allowedResponse = $middleware->handle($allowed, fn () => response('dashboard-ok'));
    expect($allowedResponse->getContent())->toBe('dashboard-ok');

    $logout = Request::create('/logout', 'POST');
    $logout->setUserResolver(fn () => $user);

    $logoutResponse = $middleware->handle($logout, fn () => response('logged-out', Response::HTTP_NO_CONTENT));
    expect($logoutResponse->getStatusCode())->toBe(Response::HTTP_NO_CONTENT);

    $turnoverStart = Request::create('/turnover/start', 'POST');
    $turnoverStart->setUserResolver(fn () => $user);
    $turnoverStartResponse = $middleware->handle($turnoverStart, fn () => response('started'));
    expect($turnoverStartResponse->getContent())->toBe('started');
});

it('keeps turnover remind-later route registered for leadership dismiss', function () {
    expect(route('turnover.remind-later', [], false))->toBe('/turnover/remind-later');
});

it('frontend turnover script blocks dismiss while portal is locked', function () {
    $js = file_get_contents(app_path('Modules/Turnover/assets/js/turnover.js'));

    expect($js)->toContain('function dismissReminderOverlay')
        ->and($js)->toContain('config.portalLocked || !config.canClose')
        ->and($js)->toContain("event.key !== 'Escape'")
        ->and($js)->toContain('triggerLogout')
        ->and($js)->toContain('remindLaterUrl');
});
