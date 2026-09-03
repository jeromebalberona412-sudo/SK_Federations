<?php

namespace App\Modules\Communications\Services;

use Illuminate\Contracts\Auth\Authenticatable;

class ParticipantTypeResolver
{
    public const KABATAAN = 'kabataan';

    public const SK_OFFICIAL = 'sk_official';

    public const SK_FED = 'sk_fed';

    /**
     * Portal this app represents for sender/caller identity.
     */
    public function portalType(): string
    {
        return (string) config('communications.portal_user_type', self::SK_FED);
    }

    public function fromUser(Authenticatable $user): string
    {
        $role = strtolower(trim((string) ($user->role ?? '')));

        return match ($role) {
            'sk_fed', 'admin' => self::SK_FED,
            'sk_official' => self::SK_OFFICIAL,
            'kabataan', 'user' => self::KABATAAN,
            default => throw new \InvalidArgumentException('Unsupported account role for messaging.'),
        };
    }

    public function normalizeSearchRole(string $role): ?string
    {
        $role = strtolower(trim($role));

        return match ($role) {
            'sk_fed' => self::SK_FED,
            'sk_official' => self::SK_OFFICIAL,
            'kabataan', 'user' => self::KABATAAN,
            default => null,
        };
    }

    public function label(string $type): string
    {
        return match ($type) {
            self::SK_FED => 'SK Federation',
            self::SK_OFFICIAL => 'SK Official',
            self::KABATAAN => 'Kabataan Member',
            default => 'User',
        };
    }

    /**
     * @return list<string>
     */
    public function searchableRoles(): array
    {
        return ['kabataan', 'user', 'sk_official', 'sk_fed'];
    }
}
