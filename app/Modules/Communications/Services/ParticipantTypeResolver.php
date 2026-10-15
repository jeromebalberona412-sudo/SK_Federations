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
            'sk_fed', 'admin' => self::SK_FED,
            'sk_official' => self::SK_OFFICIAL,
            'kabataan', 'user' => self::KABATAAN,
            default => null,
        };
    }

    public function canonicalType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return $this->normalizeSearchRole($type) ?? strtolower(trim($type));
    }

    /**
     * @return list<string>
     */
    public function equivalentTypes(?string $type): array
    {
        $canonical = $this->canonicalType($type);
        if ($canonical === self::KABATAAN) {
            return [self::KABATAAN, 'user'];
        }

        return $canonical ? [$canonical] : [];
    }

    public function typesMatch(?string $a, ?string $b): bool
    {
        $left = $this->canonicalType($a);
        $right = $this->canonicalType($b);

        return $left !== null && $right !== null && $left === $right;
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
