<?php

use App\Modules\Authentication\Models\FeatureFlag;
use App\Modules\Authentication\Notifications\SkFedResetPasswordNotification;
use App\Modules\Authentication\Services\TurnstileService;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

// Do not use RefreshDatabase here: production DB was wiped when SQLite was unavailable.

beforeEach(function () {
    config()->set('services.turnstile.enabled', true);
    config()->set('services.turnstile.site_key', 'test-site-key');
    config()->set('services.turnstile.secret_key', 'test-secret-key');
    config()->set('services.turnstile.verify_url', 'https://challenges.cloudflare.com/turnstile/v0/siteverify');

    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
    ]);
});

it('sends a password reset link for an in-scope sk fed user', function () {
    Notification::fake();

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'resettable@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => now(),
    ]);

    post(route('password.email', [], false), [
        'email' => $user->email,
        'cf-turnstile-response' => 'valid-token',
    ])->assertSessionHas('status', 'A password reset link has been sent');

    Notification::assertSentTo($user, SkFedResetPasswordNotification::class, function (SkFedResetPasswordNotification $notification) {
        expect($notification->token)->toBeString()->not->toBe('');

        return true;
    });

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();
});

it('does not send a password reset link for an out-of-scope user', function () {
    Notification::fake();

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'outofscope@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'user',
        'email_verified_at' => now(),
    ]);

    post(route('password.email', [], false), [
        'email' => $user->email,
        'cf-turnstile-response' => 'valid-token',
    ])->assertSessionHasErrors('email');

    Notification::assertNotSentTo($user, SkFedResetPasswordNotification::class);
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

it('redirects back to forgot password when reset link is missing the email parameter', function () {
    get(route('password.reset', ['token' => 'missing-email-token'], false))
        ->assertRedirect(route('password.request', [], false));
});

it('resets the password with a valid token and allows login with the new password', function () {
    FeatureFlag::query()->where('flag_key', 'features.device_verification')->update(['enabled' => false]);

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'reset-success@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => now(),
    ]);

    $token = Password::createToken($user);

    from(route('password.reset', ['token' => $token, 'email' => $user->email], false))
        ->post(route('password.update', [], false), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertRedirect(route('password.reset.success', [], false));

    $user->refresh();

    expect(Hash::check('NewPassword123!', $user->password))->toBeTrue();
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
    expect($user->remember_token)->toBeNull();

    if (Schema::hasColumn('users', 'active_session_id')) {
        expect($user->active_session_id)->toBeNull();
    }

    post('/login', [
        'email' => $user->email,
        'password' => 'NewPassword123!',
    ])->assertRedirect('/dashboard');

    assertAuthenticatedAs($user);
});

it('rejects an invalid password reset token', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'invalid-token@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => now(),
    ]);

    from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email], false))
        ->post(route('password.update', [], false), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertSessionHasErrors('email');

    expect(Hash::check('Password123!', (string) $user->fresh()->password))->toBeTrue();
});

it('rejects an expired password reset token', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'expired-token@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => now(),
    ]);

    $token = Password::createToken($user);

    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update([
            'created_at' => now()->subMinutes((int) config('auth.passwords.users.expire') + 5),
        ]);

    from(route('password.reset', ['token' => $token, 'email' => $user->email], false))
        ->post(route('password.update', [], false), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertSessionHasErrors('email');

    expect(Hash::check('Password123!', (string) $user->fresh()->password))->toBeTrue();
});

it('rejects forgot password submission when turnstile verification fails', function () {
    Notification::fake();

    config()->set('services.turnstile.enabled', true);

    $mock = \Mockery::mock(TurnstileService::class);
    $mock->shouldReceive('isConfigured')->andReturnTrue();
    $mock->shouldReceive('verify')->once()->andReturnFalse();
    app()->instance(TurnstileService::class, $mock);

    from(route('password.request', [], false))
        ->post(route('password.email', [], false), [
            'email' => 'bot-check@example.com',
            'cf-turnstile-response' => 'invalid-token',
        ])
        ->assertSessionHasErrors('cf-turnstile-response');

    Notification::assertNothingSent();
});

it('throttles forgot password requests after five attempts per minute per ip', function () {
    foreach (range(1, 5) as $_) {
        from(route('password.request', [], false))
            ->post(route('password.email', [], false), [
                'email' => 'throttle-check@example.com',
                'cf-turnstile-response' => 'valid-token',
            ])->assertStatus(302);
    }

    from(route('password.request', [], false))
        ->post(route('password.email', [], false), [
            'email' => 'throttle-check@example.com',
            'cf-turnstile-response' => 'valid-token',
        ])
        ->assertStatus(429)
        ->assertSessionHasErrors('email');
});

it('invalidates all known database sessions after a successful reset', function () {
    FeatureFlag::query()->where('flag_key', 'features.device_verification')->update(['enabled' => false]);

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $attributes = [
        'email' => 'session-reset@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => now(),
    ];

    if (Schema::hasColumn('users', 'active_session_id')) {
        $attributes['active_session_id'] = 'session-before-reset';
    }

    $user = User::factory()->create($attributes);

    if (Schema::hasTable('sessions')) {
        DB::table('sessions')->insert([
            'id' => 'session-before-reset',
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('test-payload'),
            'last_activity' => now()->timestamp,
        ]);

        expect(DB::table('sessions')->where('user_id', $user->getKey())->exists())->toBeTrue();
    }

    $token = Password::createToken($user);

    from(route('password.reset', ['token' => $token, 'email' => $user->email], false))
        ->post(route('password.update', [], false), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ComplexNewPassword123!',
            'password_confirmation' => 'ComplexNewPassword123!',
        ])
        ->assertRedirect(route('password.reset.success', [], false));

    $user->refresh();

    expect($user->remember_token)->toBeNull();

    if (Schema::hasColumn('users', 'active_session_id')) {
        expect($user->active_session_id)->toBeNull();
    }

    if (Schema::hasTable('sessions')) {
        expect(DB::table('sessions')->where('user_id', $user->getKey())->exists())->toBeFalse();
    }
});

it('requires letters numbers and symbols for reset password', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'weak-password@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => now(),
    ]);

    $token = Password::createToken($user);

    from(route('password.reset', ['token' => $token, 'email' => $user->email], false))
        ->post(route('password.update', [], false), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Password123456',
            'password_confirmation' => 'Password123456',
        ])
        ->assertSessionHasErrors('password');
});

it('sends a password reset link for an incoming turnover officer awaiting setup', function () {
    Notification::fake();

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'incoming-forgot@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_official',
        'status' => 'INACTIVE',
        'turnover_status' => 'awaiting_setup',
        'account_status' => 'turnover_pending',
        'email_verified_at' => null,
    ]);

    post(route('password.email', [], false), [
        'email' => $user->email,
        'cf-turnstile-response' => 'valid-token',
    ])->assertSessionHas('status');

    Notification::assertSentTo(
        $user,
        \App\Modules\Turnover\Notifications\TurnoverForgotPasswordSetupNotification::class,
    );
});

it('allows incoming turnover sk officials to set a password from the federation portal reset link', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'incoming-president@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_official',
        'status' => 'INACTIVE',
        'turnover_status' => 'awaiting_setup',
        'account_status' => 'turnover_pending',
        'email_verified_at' => null,
    ]);

    $token = Password::createToken($user);

    from(route('password.reset', ['token' => $token, 'email' => $user->email], false))
        ->post(route('password.update', [], false), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])
        ->assertRedirect(route('password.reset.success', [], false));

    expect(Hash::check('NewPass1!', (string) $user->fresh()->password))->toBeTrue();
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});