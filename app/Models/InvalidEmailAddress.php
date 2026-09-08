<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvalidEmailAddress extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_TEMPORARILY_INVALID = 'temporarily_invalid';

    public const STATUS_PERMANENTLY_BLOCKED = 'permanently_blocked';

    public const STATUS_VERIFIED = 'verified';

    protected $table = 'invalid_email_addresses';

    protected $fillable = [
        'email',
        'normalized_email',
        'status',
        'failure_count',
        'last_failure_reason',
        'first_detected_at',
        'last_detected_at',
        'retry_after',
        'permanently_blocked_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'failure_count' => 'integer',
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'retry_after' => 'datetime',
            'permanently_blocked_at' => 'datetime',
        ];
    }

    public function isPermanentlyBlocked(): bool
    {
        return $this->status === self::STATUS_PERMANENTLY_BLOCKED;
    }

    public function isTemporarilyBlocked(): bool
    {
        return $this->status === self::STATUS_TEMPORARILY_INVALID
            && $this->retry_after !== null
            && $this->retry_after->isFuture();
    }

    public function canRetryNow(): bool
    {
        if ($this->isPermanentlyBlocked()) {
            return false;
        }

        if ($this->status === self::STATUS_TEMPORARILY_INVALID) {
            return $this->retry_after === null || $this->retry_after->isPast();
        }

        return true;
    }
}
