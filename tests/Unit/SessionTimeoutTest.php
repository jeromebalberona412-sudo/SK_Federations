<?php

use App\Modules\Authentication\Middleware\SessionTimeout;
use App\Modules\Authentication\Services\AuthAuditLogService;
use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('session.timeout', 1);
    config()->set('session.timeout_warning_minutes', 0);
});

function makeSessionStore(array $data = []): Store
{
    $session = new Store('testing', new ArraySessionHandler(120));
    $session->start();

    foreach ($data as $key => $value) {
        $session->put($key, $value);
    }

    return $session;
}

function makeAuthenticatedRequest(
    string $uri = '/dashboard',
    string $method = 'GET',
    array $sessionData = [],
    array $headers = [],
): Request {
    $request = Request::create($uri, $method, [], [], [], $headers);
    $request->setLaravelSession(makeSessionStore($sessionData));

    $user = new User;
    $user->forceFill([
        'id' => 991001,
        'email' => 'session-timeout@example.com',
        'role' => 'sk_fed',
    ]);
    $user->syncOriginal();

    $request->setUserResolver(fn () => $user);

    return $request;
}

it('passes through guest requests without changing session activity', function () {
    Auth::shouldReceive('check')->once()->andReturn(false);

    $request = Request::create('/login', 'GET');
    $request->setLaravelSession(makeSessionStore());

    $middleware = new SessionTimeout(Mockery::mock(AuthAuditLogService::class));

    $response = $middleware->handle($request, fn () => response('public-ok'));

    expect($response->getContent())->toBe('public-ok')
        ->and($request->session()->has(SessionTimeout::SESSION_KEY))->toBeFalse();
});

it('seeds and refreshes last activity for active authenticated requests', function () {
    Auth::shouldReceive('check')->twice()->andReturn(true);

    $audit = Mockery::mock(AuthAuditLogService::class);
    $audit->shouldNotReceive('log');
    $middleware = new SessionTimeout($audit);

    $first = makeAuthenticatedRequest('/dashboard', 'GET');
    $middleware->handle($first, fn () => response('ok'));
    $seeded = (int) $first->session()->get(SessionTimeout::SESSION_KEY);
    expect($seeded)->toBeGreaterThan(0);

    $initial = now()->subSeconds(20)->getTimestamp();
    $second = makeAuthenticatedRequest('/dashboard', 'GET', [
        SessionTimeout::SESSION_KEY => $initial,
    ]);
    $middleware->handle($second, fn () => response('ok'));

    expect((int) $second->session()->get(SessionTimeout::SESSION_KEY))->toBeGreaterThan($initial);
});

it('does not refresh inactivity timestamp on heartbeat routes', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);

    Route::post('/heartbeat-test', fn () => response('ok'))->name('skfed.heartbeat');
    $initial = now()->subSeconds(20)->getTimestamp();

    $request = makeAuthenticatedRequest('/heartbeat-test', 'POST', [
        SessionTimeout::SESSION_KEY => $initial,
    ]);
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

    $middleware = new SessionTimeout(Mockery::mock(AuthAuditLogService::class));
    $middleware->handle($request, fn () => response('ok'));

    expect((int) $request->session()->get(SessionTimeout::SESSION_KEY))->toBe($initial);
});

it('logs out and redirects html requests after inactivity timeout', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);
    Auth::shouldReceive('logout')->once();

    $audit = Mockery::mock(AuthAuditLogService::class);
    $audit->shouldReceive('log')->once();

    $request = makeAuthenticatedRequest('/dashboard', 'GET', [
        SessionTimeout::SESSION_KEY => now()->subMinutes(5)->getTimestamp(),
    ]);

    $middleware = new SessionTimeout($audit);
    $response = $middleware->handle($request, fn () => response('should-not-run'));

    expect($response->isRedirect())->toBeTrue()
        ->and($response->headers->get('Location'))->toEndWith('/login')
        ->and(session('errors')?->first('session'))
        ->toBe('Your session has expired due to inactivity. Please log in again.');
});

it('returns json session_expired for ajax requests after timeout', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);
    Auth::shouldReceive('logout')->once();

    $audit = Mockery::mock(AuthAuditLogService::class);
    $audit->shouldReceive('log')->once();

    $request = makeAuthenticatedRequest(
        '/session/continue',
        'POST',
        [SessionTimeout::SESSION_KEY => now()->subMinutes(5)->getTimestamp()],
        [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ],
    );

    $middleware = new SessionTimeout($audit);
    $response = $middleware->handle($request, fn () => response('should-not-run'));

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->toMatchArray([
            'authenticated' => false,
            'session_expired' => true,
        ]);
});

it('refreshes activity for explicit continue-session style requests', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);

    $initial = now()->subSeconds(30)->getTimestamp();
    $request = makeAuthenticatedRequest('/session/continue', 'POST', [
        SessionTimeout::SESSION_KEY => $initial,
    ], [
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $middleware = new SessionTimeout(Mockery::mock(AuthAuditLogService::class));
    $middleware->handle($request, fn () => response()->json(['ok' => true]));

    expect((int) $request->session()->get(SessionTimeout::SESSION_KEY))->toBeGreaterThan($initial);
});

it('exposes a configurable timeout default of 120 minutes', function () {
    expect((int) config('session.timeout'))->toBeGreaterThan(0);

    config()->set('session.timeout', 120);
    expect((int) config('session.timeout'))->toBe(120);

    config()->set('session.timeout', 15);
    expect((int) config('session.timeout'))->toBe(15);
});
