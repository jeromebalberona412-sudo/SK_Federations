<?php

namespace App\Models;

use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbyipSubmissionScheduleHistory extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_EXTENDED = 'extended';

    public const ACTION_CANCELLED = 'cancelled';

    protected $fillable = [
        'schedule_id',
        'action',
        'old_deadline',
        'new_deadline',
        'old_date_start',
        'new_date_start',
        'reason',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'old_deadline' => 'date',
            'new_deadline' => 'date',
            'old_date_start' => 'date',
            'new_date_start' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AbyipSubmissionSchedule::class, 'schedule_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
