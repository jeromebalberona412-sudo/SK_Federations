<?php

use App\Modules\Authentication\Models\FeatureFlag;
use App\Modules\Authentication\Models\TrustedDevice;
use Illuminate\Auth\Notifications\VerifyEmail;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

// Do not use RefreshDatabase here: production DB was wiped when SQLite was unavailable.

it('allows verified sk fed user login when device verification feature is disabled', function () {
    FeatureFlag::query()->where('flag_key', 'features.device_verification')->update(['enabled' => false]);

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'federation@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => now(),
    ]);

    $response = post('/login', [
        'email' => $user->email,
        'password' => 'Password123!',
    ]);

    $response->assertRedirect('/dashboard');
    assertAuthenticatedAs($user);
});

it('blocks unverified user and sends verification email', function () {
    Notification::fake();
    FeatureFlag::query()->where('flag_key', 'features.device_verification')->update(['enabled' => false]);

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'unverified@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => null,
    ]);

    $response = from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'Password123!',
    ]);

    $response->assertRedirect(route('skfed.verification.wait'));
    assertGuest();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('requires email verification again when login device is not trusted', function () {
    Notification::fake();

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'devicechange@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => now(),
    ]);

    TrustedDevice::query()->create([
        'user_id' => $user->id,
        'fingerprint' => hash('sha256', 'different-device-fingerprint'),
        'expires_at' => now()->addDays(30),
        'last_used_at' => now()->subDay(),
    ]);

    $response = from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'Password123!',
    ]);

    $response->assertRedirect(route('skfed.verification.wait'));
    assertGuest();
    expect(session()->has('sk_fed_email_verification_pending'))->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('redirects to dashboard from wait-status once an unverified user verifies email', function () {
    Notification::fake();
    FeatureFlag::query()->where('flag_key', 'features.device_verification')->update(['enabled' => false]);

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'waitstatus@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => null,
    ]);

    from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'Password123!',
    ])->assertRedirect(route('skfed.verification.wait'));

    $pending = session('sk_fed_email_verification_pending');
    expect($pending)->toBeArray()
        ->and($pending['requires_fresh_verification'] ?? null)->toBeFalse();

    $user->forceFill(['email_verified_at' => now()])->save();

    $response = getJson(route('skfed.verification.wait.status'));

    $response->assertOk()
        ->assertJson([
            'state' => 'verified',
            'redirect' => route('dashboard'),
        ]);
    assertAuthenticatedAs($user);
});

it('redirects authenticated unverified users away from fortify verify notice', function () {
    FeatureFlag::query()->where('flag_key', 'features.device_verification')->update(['enabled' => false]);

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'stuck@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)
        ->get('/email/verify')
        ->assertRedirect(route('skfed.verification.wait'));

    assertGuest();
    expect(session()->has('sk_fed_email_verification_pending'))->toBeTrue();
});

it('allows resend verification while authenticated on verify notice flow', function () {
    Notification::fake();
    FeatureFlag::query()->where('flag_key', 'features.device_verification')->update(['enabled' => false]);

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'resend-auth@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => null,
    ]);

    $pending = [
        'user_id' => $user->id,
        'email' => $user->email,
        'started_at' => now()->subMinute()->toIso8601String(),
        'expires_at' => now()->addMinutes(15)->toIso8601String(),
        'requires_fresh_verification' => false,
    ];

    $response = $this->actingAs($user)
        ->withSession(['sk_fed_email_verification_pending' => $pending])
        ->postJson('/email/verify/resend', [
            'email' => $user->email,
            'session_key' => sha1($pending['started_at'].'|'.$pending['email']),
        ]);

    $response->assertOk()->assertJson(['ok' => true]);
    assertGuest();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('keeps wait-status pending for fresh verification until email_verified_at changes', function () {
    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $baseline = now()->subHour()->startOfSecond();

    $user = User::factory()->create([
        'email' => 'freshverify@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => $baseline,
    ]);

    $this->withSession([
        'sk_fed_email_verification_pending' => [
            'user_id' => $user->id,
            'email' => $user->email,
            'started_at' => now()->subMinute()->toIso8601String(),
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
            'requires_fresh_verification' => true,
            'verified_at_snapshot' => $baseline->toIso8601String(),
        ],
    ]);

    getJson(route('skfed.verification.wait.status'))
        ->assertOk()
        ->assertJson([
            'state' => 'pending',
        ]);

    $user->forceFill([
        'email_verified_at' => now(),
    ])->save();

    getJson(route('skfed.verification.wait.status'))
        ->assertOk()
        ->assertJson([
            'state' => 'verified',
            'redirect' => route('dashboard'),
        ]);
    assertAuthenticatedAs($user);
});
