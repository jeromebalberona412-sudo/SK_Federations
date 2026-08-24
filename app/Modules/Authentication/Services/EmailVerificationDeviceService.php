<?php

namespace App\Modules\Authentication\Services;

use App\Modules\Authentication\Models\EmailVerifiedDevice;
use App\Modules\Shared\Models\User;
use Illuminate\Http\Request;

class EmailVerificationDeviceService
{
    public const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(protected DeviceFingerprintService $fingerprintService) {}

    public function resendCooldownRemaining(array $pending): int
    {
        $lastSent = (string) ($pending['resend_last_sent_at'] ?? '');

        if ($lastSent === '') {
            return 0;
        }

        $elapsed = (int) \Illuminate\Support\Carbon::parse($lastSent)->diffInSeconds(now());

        return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
    }

    public function fingerprintFromRequest(Request $request): string
    {
        return $this->fingerprintService->fromRequest($request);
    }

    public function requiresReverification(User $user, string $fingerprint): bool
    {
        $record = EmailVerifiedDevice::query()->where('user_id', $user->getKey())->first();

        if ($record === null) {
            return false;
        }

        return ! hash_equals((string) $record->fingerprint, $fingerprint);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    public function markVerifiedDeviceFromPending(User $user, array $pending): void
    {
        $fingerprint = (string) ($pending['fingerprint'] ?? '');

        if ($fingerprint === '') {
            return;
        }

        EmailVerifiedDevice::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'fingerprint' => $fingerprint,
                'verified_at' => now(),
                'ip_address' => (string) ($pending['ip'] ?? ''),
                'user_agent' => (string) ($pending['user_agent'] ?? ''),
                'metadata' => [
                    'updated_via' => 'email_verification_wait',
                ],
            ]
        );
    }

    public function upsertCurrentDevice(User $user, Request $request): void
    {
        EmailVerifiedDevice::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'fingerprint' => $this->fingerprintFromRequest($request),
                'verified_at' => now(),
                'ip_address' => (string) $request->ip(),
                'user_agent' => (string) ($request->userAgent() ?? ''),
                'metadata' => [
                    'updated_via' => 'successful_login',
                ],
            ]
        );
    }
}
