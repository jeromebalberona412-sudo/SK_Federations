<?php

namespace App\Modules\Authentication\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerifiedDevice extends Model
{
    protected $table = 'sk_fed_email_verified_devices';

    protected $fillable = [
        'user_id',
        'fingerprint',
        'verified_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
