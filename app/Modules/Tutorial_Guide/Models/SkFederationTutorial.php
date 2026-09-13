<?php

namespace App\Modules\Tutorial_Guide\Models;

use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkFederationTutorial extends Model
{
    public const STATUS_NOT_STARTED = 'NOT_STARTED';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_SKIPPED = 'SKIPPED';

    public const DEFAULT_TUTORIAL_KEY = 'sk_federations_main_tutorial';

    protected $table = 'sk_federation_tutorials';

    protected $fillable = [
        'user_id',
        'tutorial_key',
        'current_step',
        'status',
        'started_at',
        'completed_at',
        'skipped_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isCompleted(): bool
    {
        return strtoupper((string) $this->status) === self::STATUS_COMPLETED;
    }

    public function isSkipped(): bool
    {
        return strtoupper((string) $this->status) === self::STATUS_SKIPPED;
    }

    public function isInProgress(): bool
    {
        return strtoupper((string) $this->status) === self::STATUS_IN_PROGRESS;
    }

    /**
     * True after the user has finished or skipped the mandatory first-run once.
     * Used so replay from the header can allow Skip/Close without auto-starting again.
     */
    public function hasFinishedMandatoryOnce(): bool
    {
        if ($this->completed_at !== null || $this->skipped_at !== null) {
            return true;
        }

        $status = strtoupper((string) $this->status);

        return $status === self::STATUS_COMPLETED || $status === self::STATUS_SKIPPED;
    }

    public function shouldAutoStart(): bool
    {
        return ! $this->hasFinishedMandatoryOnce();
    }
}
