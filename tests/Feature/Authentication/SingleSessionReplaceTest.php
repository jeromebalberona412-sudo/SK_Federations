<?php

use App\Modules\Authentication\Models\FeatureFlag;
use App\Modules\Authentication\Models\TrustedDevice;
use App\Modules\Authentication\Services\DeviceFingerprintService;
use Illuminate\Auth\Notifications\VerifyEmail;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\from;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

// Do not use RefreshDatabase here: production DB was wiped when SQLite was unavailable.

it('silently replaces an active session when logging in from another device', function () {
    FeatureFlag::query()->where('flag_key', 'features.device_verification')->update(['enabled' => false]);

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'concurrent@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => now(),
        'active_session_id' => 'old-session-id',
        'last_seen' => now(),
    ]);

    DB::table('sessions')->insert([
        'id' => 'old-session-id',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Old Browser',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    $response = from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'Password123!',
    ]);

    $response->assertRedirect('/dashboard');
    assertAuthenticatedAs($user);

    expect(DB::table('sessions')->where('id', 'old-session-id')->exists())->toBeFalse();
});

it('stores remember-me trusted device when checkbox is checked', function () {
    FeatureFlag::query()->where('flag_key', 'features.device_verification')->update(['enabled' => false]);

    $tenantId = (int) DB::table('tenants')->where('code', config('sk_fed_auth.tenant_code'))->value('id');

    $user = User::factory()->create([
        'email' => 'remember@example.com',
        'password' => 'Password123!',
        'tenant_id' => $tenantId,
        'role' => 'sk_fed',
        'email_verified_at' => now(),
    ]);

    $response = from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'Password123!',
        'remember' => '1',
    ]);

    $response->assertRedirect('/dashboard');
    assertAuthenticatedAs($user);

    expect(TrustedDevice::query()->where('user_id', $user->id)->exists())->toBeTrue();
});
