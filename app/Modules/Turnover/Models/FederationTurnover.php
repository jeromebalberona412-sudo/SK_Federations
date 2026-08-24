<?php

namespace App\Modules\Turnover\Models;

use App\Modules\Accounts\Models\OfficialTerm;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FederationTurnover extends Model
{
    public const STATUS_PENDING_REGISTRATION = 'pending_registration';

    public const STATUS_PENDING_ACCOUNT_SETUP = 'pending_account_setup';

    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING_REGISTRATION,
        self::STATUS_PENDING_ACCOUNT_SETUP,
        self::STATUS_PENDING_CONFIRMATION,
    ];

    protected $fillable = [
        'tenant_id',
        'current_term_id',
        'new_term_id',
        'previous_president_id',
        'previous_vice_president_id',
        'new_president_id',
        'new_vice_president_id',
        'started_by',
        'confirmed_by',
        'status',
        'started_at',
        'confirmed_at',
        'started_ip',
        'started_user_agent',
        'confirmed_ip',
        'confirmed_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function currentTerm(): BelongsTo
    {
        return $this->belongsTo(OfficialTerm::class, 'current_term_id');
    }

    public function newTerm(): BelongsTo
    {
        return $this->belongsTo(OfficialTerm::class, 'new_term_id');
    }

    public function previousPresident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_president_id');
    }

    public function previousVicePresident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_vice_president_id');
    }

    public function newPresident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_president_id');
    }

    public function newVicePresident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_vice_president_id');
    }

    public function startedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(FederationTurnoverRegistration::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isEditable(): bool
    {
        return $this->status !== self::STATUS_COMPLETED
            && $this->status !== self::STATUS_CANCELLED;
    }
}
