<?php

namespace App\Modules\Profile\Models;

use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialProfile extends Model
{
    public const POSITION_CHAIRMAN = 'Chairman';
    public const POSITION_COUNCILOR = 'Councilor';
    public const POSITION_KAGAWAD = 'Kagawad';
    public const POSITION_TREASURER = 'Treasurer';
    public const POSITION_SECRETARY = 'Secretary';
    public const POSITION_AUDITOR = 'Auditor';
    public const POSITION_PIO = 'PIO';

    public const POSITIONS = [
        self::POSITION_CHAIRMAN,
        self::POSITION_COUNCILOR,
        self::POSITION_KAGAWAD,
        self::POSITION_TREASURER,
        self::POSITION_SECRETARY,
        self::POSITION_AUDITOR,
        self::POSITION_PIO,
    ];

    protected $table = 'official_profiles';

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
        'municipality',
        'province',
        'region',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'age' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
