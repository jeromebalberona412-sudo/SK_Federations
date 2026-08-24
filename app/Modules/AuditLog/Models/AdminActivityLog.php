<?php

namespace App\Modules\AuditLog\Models;

use App\Modules\Shared\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Modules\Shared\Models\User;

class AdminActivityLog extends Model
{
    /**
     * Disable updated_at timestamp (append-only log).
     */
    const UPDATED_AT = null;

    /**
     * The primary key type is UUID (string).
     */
    protected $keyType = 'string';

    /**
     * Primary key is not auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'event_type',
        'action',
        'entity_type',
        'entity_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot method to generate UUID for new records.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        // Prevent updates and deletes to maintain immutability
        static::updating(function ($model) {
            return false;
        });

        static::deleting(function ($model) {
            return false;
        });
    }

    /**
     * Relationship: Belongs to User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Event type constants for consistency.
     */
    const EVENT_LOGIN_SUCCESS = 'login_success';
    const EVENT_LOGIN_FAILED = 'login_failed';
    const EVENT_FIRST_LOGIN = 'first_login';
    const EVENT_PASSWORD_SETUP = 'password_setup';
    const EVENT_ACCOUNT_LOCKED = 'account_locked';
    const EVENT_2FA_CHALLENGE_FAILED = 'two_factor_challenge_failed';
    const EVENT_2FA_CHALLENGE_PASSED = 'two_factor_challenge_passed';
    const EVENT_2FA_ENABLED = 'two_factor_enabled';
    const EVENT_2FA_DISABLED = 'two_factor_disabled';
    const EVENT_PASSWORD_CHANGED = 'password_changed';
    const EVENT_PASSWORD_RESET_REQUESTED = 'password_reset_requested';
    const EVENT_PASSWORD_RESET_COMPLETED = 'password_reset_completed';
    const EVENT_EMAIL_VERIFIED = 'email_verified';
    const EVENT_LOGOUT = 'logout';
    const EVENT_TRUSTED_DEVICE_REGISTERED = 'trusted_device_registered';
    const EVENT_DEVICE_VERIFICATION_SUCCESS = 'device_verification_success';
    const EVENT_SUSPICIOUS_LOGIN_DETECTED = 'suspicious_login_detected';

    /**
     * @return array<int, string>
     */
    public static function securityEventTypes(): array
    {
        return [
            self::EVENT_LOGIN_FAILED,
            self::EVENT_ACCOUNT_LOCKED,
            self::EVENT_PASSWORD_CHANGED,
            self::EVENT_PASSWORD_RESET_REQUESTED,
            self::EVENT_PASSWORD_RESET_COMPLETED,
            self::EVENT_TRUSTED_DEVICE_REGISTERED,
            self::EVENT_DEVICE_VERIFICATION_SUCCESS,
            self::EVENT_SUSPICIOUS_LOGIN_DETECTED,
            self::EVENT_2FA_CHALLENGE_FAILED,
        ];
    }

    public static function isSecurityEvent(?string $eventType): bool
    {
        if (! is_string($eventType) || $eventType === '') {
            return false;
        }

        if (in_array($eventType, self::securityEventTypes(), true)) {
            return true;
        }

        return str_starts_with($eventType, 'security.');
    }
}
