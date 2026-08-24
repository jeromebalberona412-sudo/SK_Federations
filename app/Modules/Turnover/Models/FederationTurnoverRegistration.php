<?php

namespace App\Modules\Turnover\Models;

use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FederationTurnoverRegistration extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_INVITED = 'invited';

    public const STATUS_ACCOUNT_CREATED = 'account_created';

    public const STATUS_ACTIVATED = 'activated';

    protected $fillable = [
        'federation_turnover_id',
        'user_id',
        'official_term_id',
        'position',
        'complete_name',
        'email',
        'contact_number',
        'municipality',
        'term_start',
        'term_end',
        'status',
        'invited_at',
        'account_setup_completed_at',
        'activated_at',
        'registration_payload',
    ];

    protected function casts(): array
    {
        return [
            'term_start' => 'date',
            'term_end' => 'date',
            'invited_at' => 'datetime',
            'account_setup_completed_at' => 'datetime',
            'activated_at' => 'datetime',
            'registration_payload' => 'array',
        ];
    }

    public function turnover(): BelongsTo
    {
        return $this->belongsTo(FederationTurnover::class, 'federation_turnover_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officialTerm(): BelongsTo
    {
        return $this->belongsTo(OfficialTerm::class);
    }

    public function hasCompletedAccountSetup(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACCOUNT_CREATED,
            self::STATUS_ACTIVATED,
        ], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending Invite',
            self::STATUS_INVITED => 'Awaiting Setup',
            self::STATUS_ACCOUNT_CREATED => 'Setup Complete',
            self::STATUS_ACTIVATED => 'Activated',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function getDisplayCompleteNameAttribute(): string
    {
        $payload = is_array($this->registration_payload) ? $this->registration_payload : [];

        if ($this->hasNamePartsInPayload($payload)) {
            return self::formatFullName(
                $payload['first_name'] ?? null,
                $payload['middle_name'] ?? null,
                $payload['last_name'] ?? null,
                $payload['suffix'] ?? null,
            );
        }

        $profile = $this->user?->officialProfile;

        if ($profile) {
            return self::formatFullName(
                $profile->first_name,
                $profile->middle_name,
                $profile->last_name,
                $profile->suffix,
            );
        }

        return (string) ($this->attributes['complete_name'] ?? '');
    }

    public static function formatFullName(
        ?string $firstName,
        ?string $middleName,
        ?string $lastName,
        ?string $suffix = null,
    ): string {
        $suffix = self::normalizeSuffixForDisplay($suffix);

        return trim(implode(' ', array_filter([
            self::normalizeNamePart($firstName),
            self::normalizeNamePart($middleName),
            self::normalizeNamePart($lastName),
            $suffix,
        ], static fn (?string $part): bool => $part !== null && $part !== '')));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasNamePartsInPayload(array $payload): bool
    {
        return trim((string) ($payload['first_name'] ?? '')) !== ''
            || trim((string) ($payload['middle_name'] ?? '')) !== ''
            || trim((string) ($payload['last_name'] ?? '')) !== '';
    }

    private static function normalizeNamePart(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_strtoupper($trimmed, 'UTF-8');
    }

    private static function normalizeSuffixForDisplay(?string $suffix): ?string
    {
        if ($suffix === null) {
            return null;
        }

        $trimmed = trim($suffix);

        if ($trimmed === '' || strtoupper($trimmed) === 'NONE') {
            return null;
        }

        return $trimmed;
    }
}
