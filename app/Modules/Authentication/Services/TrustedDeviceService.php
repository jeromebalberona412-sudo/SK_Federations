<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    public const REMEMBER_COOKIE_NAME = 'sk_fed_remember_device';

    protected function tableName(): string
    {
        return 'sk_fed_trusted_devices';
    }

    public function isTrusted(User $user, Request $request): bool
    {
        if (! Schema::hasTable($this->tableName())) {
            return false;
        }

        if ($this->matchesRememberCookie($user, $request)) {
            return true;
        }

        $fingerprint = app(DeviceFingerprintService::class)->fingerprint($request);

        $trusted = DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('fingerprint', $fingerprint)
            ->where('expires_at', '>', now())
            ->exists();

        if ($trusted) {
            $this->touchTrustedDevice($user, $request, $fingerprint);
        }

        return $trusted;
    }

    public function rememberDevice(User $user, Request $request, ?string $loginFingerprint = null): void
    {
        if (! Schema::hasTable($this->tableName())) {
            return;
        }

        $plainToken = Str::random(64);
        $expirationDays = (int) config('sk_fed_auth.trusted_device.expiration_days', 30);
        $expiresAt = now()->addDays($expirationDays);
        $currentFingerprint = app(DeviceFingerprintService::class)->fingerprint($request);

        $this->persistRememberDevice($user, $request, $currentFingerprint, $plainToken, $expiresAt);

        if (
            $loginFingerprint !== null
            && $loginFingerprint !== ''
            && $loginFingerprint !== $currentFingerprint
        ) {
            $this->persistRememberDevice($user, $request, $loginFingerprint, $plainToken, $expiresAt);
        }

        Cookie::queue($this->makeRememberCookie($user, $plainToken, $expiresAt));
    }

    public function refreshRememberCookieIfPresent(User $user, Request $request): void
    {
        if (! Schema::hasTable($this->tableName()) || ! $this->hasColumn('device_token_hash')) {
            return;
        }

        $cookieValue = (string) $request->cookie(self::REMEMBER_COOKIE_NAME, '');

        if ($cookieValue === '' || ! str_contains($cookieValue, '|')) {
            return;
        }

        [$cookieUserId, $plainToken] = explode('|', $cookieValue, 2);
        $plainToken = trim($plainToken);

        if ((int) $cookieUserId !== (int) $user->getKey() || $plainToken === '') {
            return;
        }

        $tokenHash = hash('sha256', $plainToken);
        $record = DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('device_token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            return;
        }

        $expirationDays = (int) config('sk_fed_auth.trusted_device.expiration_days', 30);
        $expiresAt = now()->addDays($expirationDays);
        $fingerprint = app(DeviceFingerprintService::class)->fingerprint($request);

        $this->syncTrustedDeviceRecord($user, $request, $record, $fingerprint, $expiresAt);

        Cookie::queue($this->makeRememberCookie($user, $plainToken, $expiresAt));
    }

    public function trust(User $user, Request $request): void
    {
        if (! Schema::hasTable($this->tableName())) {
            return;
        }

        $fingerprint = app(DeviceFingerprintService::class)->fingerprint($request);
        $expirationDays = (int) config('sk_fed_auth.trusted_device.expiration_days', 30);

        DB::table($this->tableName())->updateOrInsert(
            [
                'user_id' => $user->getKey(),
                'fingerprint' => $fingerprint,
            ],
            [
                'user_agent' => (string) ($request->userAgent() ?? ''),
                'ip_address' => (string) $request->ip(),
                'expires_at' => now()->addDays($expirationDays),
                'last_used_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function touch(User $user, Request $request): void
    {
        $this->touchTrustedDevice($user, $request);
    }

    public function forgetRememberCookie(): void
    {
        Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE_NAME));
    }

    protected function matchesRememberCookie(User $user, Request $request): bool
    {
        if (! $this->hasColumn('device_token_hash')) {
            return false;
        }

        $cookieValue = (string) $request->cookie(self::REMEMBER_COOKIE_NAME, '');

        if ($cookieValue === '' || ! str_contains($cookieValue, '|')) {
            return false;
        }

        [$cookieUserId, $plainToken] = explode('|', $cookieValue, 2);
        $cookieUserId = (int) $cookieUserId;
        $plainToken = trim($plainToken);

        if ($cookieUserId !== (int) $user->getKey() || $plainToken === '') {
            return false;
        }

        $tokenHash = hash('sha256', $plainToken);
        $record = DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('device_token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            return false;
        }

        $fingerprint = app(DeviceFingerprintService::class)->fingerprint($request);

        $this->syncTrustedDeviceRecord($user, $request, $record, $fingerprint);

        return true;
    }

    /**
     * @param  object  $record
     */
    protected function syncTrustedDeviceRecord(
        User $user,
        Request $request,
        object $record,
        string $fingerprint,
        $expiresAt = null,
    ): void {
        $updates = [
            'last_used_at' => now(),
            'ip_address' => (string) $request->ip(),
            'user_agent' => (string) ($request->userAgent() ?? ''),
            'updated_at' => now(),
        ];

        if ($expiresAt !== null) {
            $updates['expires_at'] = $expiresAt;
        }

        if ((string) ($record->fingerprint ?? '') === $fingerprint) {
            DB::table($this->tableName())
                ->where('id', $record->id)
                ->update($updates);

            return;
        }

        $existingByFingerprint = DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('fingerprint', $fingerprint)
            ->where('id', '!=', $record->id)
            ->first();

        if ($existingByFingerprint !== null) {
            $mergeUpdates = $updates;

            if ($this->hasColumn('device_token_hash') && ! empty($record->device_token_hash)) {
                $mergeUpdates['device_token_hash'] = $record->device_token_hash;
            }

            DB::table($this->tableName())
                ->where('id', $existingByFingerprint->id)
                ->update($mergeUpdates);

            DB::table($this->tableName())
                ->where('id', $record->id)
                ->delete();

            return;
        }

        DB::table($this->tableName())
            ->where('id', $record->id)
            ->update(array_merge($updates, [
                'fingerprint' => $fingerprint,
            ]));
    }

    protected function persistRememberDevice(
        User $user,
        Request $request,
        string $fingerprint,
        string $plainToken,
        $expiresAt,
    ): void {
        $payload = [
            'user_agent' => (string) ($request->userAgent() ?? ''),
            'ip_address' => (string) $request->ip(),
            'expires_at' => $expiresAt,
            'last_used_at' => now(),
            'updated_at' => now(),
            'metadata' => json_encode([
                'source' => 'remember_me',
                'cookie_name' => self::REMEMBER_COOKIE_NAME,
            ]),
        ];

        if ($this->hasColumn('device_token_hash')) {
            $payload['device_token_hash'] = hash('sha256', $plainToken);
        }

        $existing = DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existing === null) {
            DB::table($this->tableName())->insert(array_merge($payload, [
                'user_id' => $user->getKey(),
                'fingerprint' => $fingerprint,
                'created_at' => now(),
            ]));
        } else {
            DB::table($this->tableName())
                ->where('id', $existing->id)
                ->update($payload);
        }
    }

    protected function touchTrustedDevice(User $user, Request $request, ?string $fingerprint = null): void
    {
        if (! Schema::hasTable($this->tableName())) {
            return;
        }

        $fingerprint ??= app(DeviceFingerprintService::class)->fingerprint($request);

        DB::table($this->tableName())
            ->where('user_id', $user->getKey())
            ->where('fingerprint', $fingerprint)
            ->update([
                'last_used_at' => now(),
                'ip_address' => (string) $request->ip(),
                'user_agent' => (string) ($request->userAgent() ?? ''),
                'updated_at' => now(),
            ]);
    }

    protected function makeRememberCookie(User $user, string $plainToken, $expiresAt)
    {
        $minutes = max(1, now()->diffInMinutes($expiresAt, false));

        return cookie(
            name: self::REMEMBER_COOKIE_NAME,
            value: $user->getKey().'|'.$plainToken,
            minutes: $minutes,
            path: '/',
            domain: null,
            secure: (bool) config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    protected function hasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn($this->tableName(), $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
