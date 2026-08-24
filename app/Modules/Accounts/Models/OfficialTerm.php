<?php

namespace App\Modules\Accounts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialTerm extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_REPLACED = 'REPLACED';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_REPLACED,
    ];

    protected $fillable = [
        'official_profile_id',
        'term_start',
        'term_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'term_start' => 'date',
            'term_end' => 'date',
        ];
    }

    public function officialProfile(): BelongsTo
    {
        return $this->belongsTo(OfficialProfile::class);
    }
}
