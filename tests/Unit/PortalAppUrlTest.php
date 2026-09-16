<?php

use App\Modules\Accounts\Support\PortalAppUrl;
use Tests\TestCase;

uses(TestCase::class);

it('prefers non-loopback urls and never returns localhost in production', function () {
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.url' => 'http://localhost:8001',
        'app.public_url' => 'https://federation.example.com',
        'services.sk_fed_app_url' => 'https://federation.example.com',
        'services.kabataan_app_url' => 'http://localhost:8002',
        'services.sk_officials_app_url' => 'https://officials.example.com',
    ]);

    expect(PortalAppUrl::selfApp())->toBe('https://federation.example.com');
    expect(PortalAppUrl::kabataan())->toBe('');
    expect(PortalAppUrl::preferAbsolute([
        config('services.kabataan_app_url'),
    ]))->toBeNull();
    expect(PortalAppUrl::preferAbsolute([
        config('services.sk_officials_app_url'),
    ]))->toBe('https://officials.example.com');
});

it('joins portal paths without leaking localhost', function () {
    $url = PortalAppUrl::join('https://kabataan.example.com', '/dashboard/comments/55');

    expect($url)->toBe('https://kabataan.example.com/dashboard/comments/55');
});

it('rewrites localhost mail links to the public federation domain', function () {
    config([
        'app.url' => 'http://localhost:8001',
        'app.public_url' => 'https://federation.example.com',
        'services.sk_fed_app_url' => 'https://federation.example.com',
    ]);

    expect(PortalAppUrl::selfApp())->toBe('https://federation.example.com')
        ->and(PortalAppUrl::publicBase('http://localhost:8001'))->toBe('https://federation.example.com')
        ->and(PortalAppUrl::mailLink('http://127.0.0.1:8001/reset-password/demo-token?email=a%40b.com'))
        ->toBe('https://federation.example.com/reset-password/demo-token?email=a%40b.com')
        ->and(PortalAppUrl::mailLink('/login'))->toBe('https://federation.example.com/login')
        ->and(PortalAppUrl::mailLink('https://kabataan.example.com/dashboard/comments/9'))
        ->toBe('https://kabataan.example.com/dashboard/comments/9');
});

it('rewrites lan ip mail links to the configured app url', function () {
    config([
        'app.url' => 'https://federation.example.com',
        'app.public_url' => null,
        'services.sk_fed_app_url' => 'https://federation.example.com',
    ]);

    expect(PortalAppUrl::isNonPublic('http://192.168.56.1:8000'))->toBeTrue()
        ->and(PortalAppUrl::mailLink('http://192.168.56.1:8000/reset-password/demo-token'))
        ->toBe('https://federation.example.com/reset-password/demo-token')
        ->and(PortalAppUrl::selfApp())->toBe('https://federation.example.com')
        ->and(PortalAppUrl::selfApp())->not->toContain('192.168.')
        ->and(PortalAppUrl::selfApp())->not->toContain('localhost');
});

it('does not treat an officials portal url as the federation host', function () {
    config([
        'app.url' => 'https://federation.example.com',
        'app.public_url' => 'https://federation.example.com',
        'services.sk_fed_app_url' => 'https://federation.example.com',
        'services.sk_officials_app_url' => 'https://officials.example.com',
    ]);

    expect(PortalAppUrl::publicBase('https://officials.example.com'))
        ->toBe('https://officials.example.com')
        ->and(PortalAppUrl::officials())->toBe('https://officials.example.com')
        ->and(PortalAppUrl::federation())->toBe('https://federation.example.com');
});

it('falls back to the current public request host when portal urls are missing', function () {
    app()->detectEnvironment(fn () => 'production');
    config([
        'app.url' => 'http://localhost',
        'app.public_url' => null,
        'services.sk_fed_app_url' => null,
    ]);

    $request = Illuminate\Http\Request::create(
        'https://skfederations.skoneportal.com/forgot-password',
        'POST'
    );
    app()->instance('request', $request);

    expect(PortalAppUrl::selfApp())->toBe('https://skfederations.skoneportal.com')
        ->and(PortalAppUrl::absoluteRoute('password.reset', [
            'token' => 'demo-token',
            'email' => 'a@b.com',
        ]))->toStartWith('https://skfederations.skoneportal.com/');
});
