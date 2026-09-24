<?php

use App\Modules\Accounts\Notifications\AccountResetPasswordNotification;
use App\Modules\Accounts\Notifications\FederationPositionAssignedNotification;
use App\Modules\Accounts\Services\AccountSetupService;
use App\Modules\Accounts\Support\PortalAppUrl;
use App\Modules\Authentication\Notifications\SkFedResetPasswordNotification;
use App\Modules\KabataanMonitoring\Notifications\KabataanAccountInviteNotification;
use App\Modules\Profile\Notifications\EmailChangeVerificationNotification;
use App\Modules\Profile\Notifications\PasswordChangeVerificationNotification;
use App\Modules\Shared\Models\User;
use App\Modules\Turnover\Notifications\TurnoverAccountSetupNotification;
use App\Modules\Turnover\Notifications\TurnoverCompletedNotification;
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
    expect($baseUrl)->toBe(PortalAppUrl::CANONICAL_OFFICIALS_URL);
});

it('falls back to the canonical officials url when SK_OFFICIALS_APP_URL is empty', function () {
    config([
        'services.sk_officials_app_url' => null,
        'services.sk_fed_app_url' => 'https://federations.skoneportal.com',
        'app.url' => 'https://federations.skoneportal.com',
    ]);

    [$label, $baseUrl] = app(AccountSetupService::class)->resolveSetupTarget(
        new User(['role' => User::ROLE_SK_OFFICIAL])
    );

    expect($label)->toBe('SK Official')
        ->and($baseUrl)->toBe('https://skofficials.skoneportal.com')
        ->and(PortalAppUrl::officialsOrFail())->toBe('https://skofficials.skoneportal.com');
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
        'https://skofficials.skoneportal.com/activate-account/demo-token?email=official%40example.com'
    );
});

function configureLiveFederationDomain(): void
{
    config([
        'app.url' => 'http://localhost:8001',
        'app.public_url' => 'https://federation.example.com',
        'services.sk_fed_app_url' => 'https://federation.example.com',
        'services.sk_officials_app_url' => 'https://officials.example.com',
        'services.kabataan_app_url' => 'https://kabataan.example.com',
    ]);
}

it('does not put localhost into password reset emails when a public domain is configured', function () {
    configureLiveFederationDomain();

    $user = new User([
        'email' => 'fed@example.com',
        'name' => 'Fed User',
    ]);

    $mail = (new SkFedResetPasswordNotification('demo-token'))->toMail($user);

    expect($mail->actionUrl)
        ->toStartWith('https://federation.example.com/')
        ->and($mail->actionUrl)->toContain('demo-token')
        ->and($mail->actionUrl)->not->toContain('localhost')
        ->and($mail->actionUrl)->not->toContain('127.0.0.1');
});

it('rewrites localhost account setup bases to the live portal domain', function () {
    configureLiveFederationDomain();

    $user = new User([
        'email' => 'fed@example.com',
        'name' => 'Fed User',
    ]);

    $mail = (new AccountResetPasswordNotification(
        'demo-token',
        'http://localhost:8001',
        'SK Federation',
        false,
    ))->toMail($user);

    expect($mail->actionUrl)
        ->toStartWith('https://federation.example.com/reset-password/demo-token')
        ->and($mail->actionUrl)->not->toContain('localhost');
});

it('puts the live federation domain in turnover setup emails', function () {
    configureLiveFederationDomain();

    $user = new User([
        'email' => 'incoming@example.com',
        'name' => 'Incoming Chair',
    ]);

    $mail = (new TurnoverAccountSetupNotification(
        'demo-token',
        'http://localhost:8001',
        'President',
    ))->toMail($user);

    expect($mail->actionUrl)
        ->toStartWith('https://federation.example.com/reset-password/demo-token')
        ->and($mail->actionUrl)->toContain('email=incoming%40example.com')
        ->and($mail->actionUrl)->not->toContain('localhost');
});

it('puts the live federation domain in turnover completed emails', function () {
    configureLiveFederationDomain();

    $user = new User([
        'email' => 'incoming@example.com',
        'name' => 'Incoming Chair',
    ]);

    $mail = (new TurnoverCompletedNotification(
        'http://127.0.0.1:8001'
    ))->toMail($user);

    expect($mail->actionUrl)->toBe('https://federation.example.com/login')
        ->and($mail->actionUrl)->not->toContain('localhost')
        ->and($mail->actionUrl)->not->toContain('127.0.0.1');
});

it('puts the live kabataan domain in account invite emails', function () {
    configureLiveFederationDomain();

    $mail = (new KabataanAccountInviteNotification(
        'Juan Dela Cruz',
        PortalAppUrl::join(PortalAppUrl::kabataan(), 'kkprofiling/account-invite/12/plain-token'),
    ))->toMail(new User(['email' => 'youth@example.com']));

    expect($mail->actionUrl)
        ->toBe('https://kabataan.example.com/kkprofiling/account-invite/12/plain-token')
        ->and($mail->actionUrl)->not->toContain('localhost');
});

it('puts the live federation domain in email-change confirmation emails', function () {
    configureLiveFederationDomain();

    $user = new User([
        'email' => 'old@example.com',
        'pending_email' => 'new@example.com',
        'name' => 'Fed User',
    ]);
    $user->id = 15;

    $mail = (new EmailChangeVerificationNotification($user, 'plain-token'))
        ->toMail($user);

    expect($mail->actionUrl)
        ->toStartWith('https://federation.example.com/change-email/confirm/15/plain-token')
        ->and($mail->actionUrl)->not->toContain('localhost');
});

it('puts the live federation domain in password-change confirmation emails', function () {
    configureLiveFederationDomain();

    $user = new User([
        'email' => 'fed@example.com',
        'name' => 'Fed User',
    ]);
    $user->id = 16;

    $mail = (new PasswordChangeVerificationNotification('plain-token'))
        ->toMail($user);

    expect($mail->actionUrl)
        ->toStartWith('https://federation.example.com/change-password/confirm/16/plain-token')
        ->and($mail->actionUrl)->not->toContain('localhost');
});
