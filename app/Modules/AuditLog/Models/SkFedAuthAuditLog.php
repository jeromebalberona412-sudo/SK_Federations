<?php

namespace App\Modules\AuditLog\Models;

use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkFedAuthAuditLog extends Model
{
    protected $table = 'sk_fed_auth_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'actor_email',
        'event',
        'outcome',
        'resource_type',
        'resource_id',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}