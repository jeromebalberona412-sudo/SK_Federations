<?php

uses(Tests\TestCase::class);

use App\Modules\Accounts\Support\PortalAppUrl;

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
