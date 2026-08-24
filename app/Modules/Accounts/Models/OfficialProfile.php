<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OfficialProfile extends Model
{
    use HasFactory;

    public const FEDERATION_POSITIONS = [
        'President',
        'Vice President',
        'Secretary',
        'Treasurer',
        'PIO',
        'Sergeant at Arms',
    ];

    /** Federation portal login is limited to these assigned roles for SK Officials chairpersons. */
    public const FEDERATION_PORTAL_ACCESS_POSITIONS = [
        'President',
        'Vice President',
    ];

    public const OFFICIAL_POSITIONS = [
        'Chairperson',
        'Secretary',
        'Treasurer',
        'Kagawad',
    ];

    /** @deprecated Use federationPositionOptions() or officialPositionOptions() */
    public const POSITIONS = [
        ...self::FEDERATION_POSITIONS,
        ...self::OFFICIAL_POSITIONS,
        'Councilor',
        'Auditor',
    ];

    public static function federationPositionOptions(): array
    {
        return [
            'President' => 'President',
            'Vice President' => 'Vice President',
            'Secretary' => 'Secretary',
            'Treasurer' => 'Treasurer',
            'PIO' => 'PIO',
            'Sergeant at Arms' => 'Sergeant at Arms',
        ];
    }

    public static function officialPositionOptions(): array
    {
        return [
            'Chairperson' => 'SK Chairperson',
            'Secretary' => 'SK Secretary',
            'Treasurer' => 'SK Treasurer',
            'Kagawad' => 'SK Kagawad',
        ];
    }

    public static function positionsForRole(string $role): array
    {
        return $role === \App\Modules\Shared\Models\User::ROLE_SK_FED
            ? self::FEDERATION_POSITIONS
            : self::OFFICIAL_POSITIONS;
    }

    /**
     * @return array<int, string>
     */
    public static function allowedPositions(): array
    {
        return array_values(array_unique([
            ...self::FEDERATION_POSITIONS,
            ...self::OFFICIAL_POSITIONS,
            'Councilor',
            'Auditor',
        ]));
    }

    protected $fillable = [
        'tenant_id',
        'user_id',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'sex',
        'date_of_birth',
        'age',
        'contact_number',
        'position',
        'federation_position',
        'municipality',
        'province',
        'region',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'age' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(OfficialTerm::class);
    }

    public function latestTerm(): HasOne
    {
        return $this->hasOne(OfficialTerm::class)->latestOfMany('term_end');
    }

    public function displayFederationPosition(): ?string
    {
        if ($this->federation_position !== null && $this->federation_position !== '') {
            return $this->federation_position;
        }

        if ($this->user?->role === User::ROLE_SK_FED) {
            return $this->position ?: null;
        }

        return null;
    }

    public function isChairpersonPosition(): bool
    {
        return in_array($this->position, ['Chairperson', 'Chairman'], true);
    }
}
