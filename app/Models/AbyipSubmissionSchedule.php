<?php

namespace App\Models;

use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AbyipSubmissionSchedule extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->getIncrementing() && empty($model->getKey())) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function getKeyType(): string
    {
        return $this->usesUuidKey() ? 'string' : 'int';
    }

    public function getIncrementing(): bool
    {
        return ! $this->usesUuidKey();
    }

    private function usesUuidKey(): bool
    {
        static $usesUuid = null;

        if ($usesUuid === null) {
            $usesUuid = Schema::hasTable($this->getTable())
                && in_array(Schema::getColumnType($this->getTable(), $this->getKeyName()), ['uuid', 'guid'], true);
        }

        return $usesUuid;
    }

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_ONGOING = 'ongoing';

    public const STATUS_EXTENDED = 'extended_submission_period';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'fiscal_year',
        'title',
        'date_start',
        'deadline',
        'original_deadline',
        'status',
        'allow_late_extension',
        'created_by_user_id',
        'updated_by_user_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'date_start' => 'date',
            'deadline' => 'date',
            'original_deadline' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function allowLateExtension(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            set: fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AbyipSubmissionScheduleHistory::class, 'schedule_id')->orderByDesc('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
