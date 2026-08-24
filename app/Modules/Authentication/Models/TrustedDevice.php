<?php

namespace App\Modules\Authentication\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    protected $table = 'sk_fed_trusted_devices';

    protected $fillable = [
        'user_id',
        'fingerprint',
        'ip_address',
        'user_agent',
        'last_used_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
