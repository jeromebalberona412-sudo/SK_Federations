<?php

use App\Modules\Accounts\Notifications\AccountResetPasswordNotification;
use App\Modules\Accounts\Notifications\FederationPositionAssignedNotification;
use App\Modules\Accounts\Services\AccountSetupService;
use App\Modules\Accounts\Support\PortalAppUrl;
use App\Modules\Authentication\Notifications\SkFedResetPasswordNotification;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\Notification;

it('prepends https to bare hostnames so they are not treated as paths', function () {
    expect(PortalAppUrl::normalize('skoneportal-com-739030.hostingersite.com'))
        ->toBe('https://skoneportal-com-739030.hostingersite.com');

    expect(PortalAppUrl::normalize('/skoneportal-com-739030.hostingersite.com/'))
        ->toBe('https://skoneportal-com-739030.hostingersite.com');
});

it('builds absolute activation urls even when env omits the scheme', function () {
    $url = PortalAppUrl::join(
        'skoneportal-com-739030.hostingersite.com',
        'activate-account/demo-token',
        ['email' => 'user@example.com']
    );

    expect($url)->toBe(
        'https://skoneportal-com-739030.hostingersite.com/activate-account/demo-token?email=user%40example.com'
    );
    expect($url)->not->toStartWith('/');
});

it('resolves officials setup target to an absolute https url', function () {
    config([
        'services.sk_officials_app_url' => 'skoneportal-com-739030.hostingersite.com',
        'services.sk_fed_app_url' => 'https://federation.example',
        'app.url' => 'https://federation.example',
    ]);

    [$label, $baseUrl] = app(AccountSetupService::class)->resolveSetupTarget(
        new User(['role' => User::ROLE_SK_OFFICIAL])
    );

    expect($label)->toBe('SK Official');
    expect($baseUrl)->toBe('https://skoneportal-com-739030.hostingersite.com');
});

it('uses an absolute https portal url in federation position assignment emails', function () {
    config([
        'app.url' => 'skoneportal-com-446837.hostingersite.com',
        'app.public_url' => null,
        'services.sk_fed_app_url' => 'skoneportal-com-446837.hostingersite.com',
    ]);

    $user = new User([
        'email' => 'chair@example.com',
        'name' => 'Test Chair',
    ]);

    $mail = (new FederationPositionAssignedNotification(
        'President',
        'skoneportal-com-446837.hostingersite.com',
    ))->toMail($user);

    expect($mail->actionUrl)->toBe('https://skoneportal-com-446837.hostingersite.com')
        ->and($mail->actionUrl)->not->toContain('google.com')
        ->and($mail->actionUrl)->toStartWith('https://');
});

it('builds password reset emails with the federation domain when env omits the scheme', function () {
    config([
        'app.url' => 'skoneportal-com-446837.hostingersite.com',
        'app.public_url' => null,
        'services.sk_fed_app_url' => 'skoneportal-com-446837.hostingersite.com',
    ]);

    $user = new User([
        'email' => 'fed@example.com',
        'name' => 'Fed User',
    ]);

    $mail = (new SkFedResetPasswordNotification('demo-token'))
        ->toMail($user);

    expect($mail->actionUrl)
        ->toStartWith('https://skoneportal-com-446837.hostingersite.com/')
        ->and($mail->actionUrl)->not->toContain('google.com')
        ->and($mail->actionUrl)->not->toStartWith('/');
});

it('puts an absolute activation link into the setup email notification', function () {
    Notification::fake();

    config([
        'services.sk_officials_app_url' => 'skoneportal-com-739030.hostingersite.com',
        'services.sk_fed_app_url' => 'https://federation.example',
        'app.url' => 'https://federation.example',
    ]);

    $user = new User([
        'role' => User::ROLE_SK_OFFICIAL,
        'email' => 'official@example.com',
        'name' => 'Test Official',
    ]);

    $notification = new AccountResetPasswordNotification(
        'demo-token',
        PortalAppUrl::officialsOrFail(),
        'SK Official',
        true,
    );

    $mail = $notification->toMail($user);

    expect($mail->actionUrl)->toBe(
        'https://skoneportal-com-739030.hostingersite.com/activate-account/demo-token?email=official%40example.com'
    );
});
