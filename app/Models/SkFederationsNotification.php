<?php

namespace App\Models;

use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkFederationsNotification extends Model
{
    protected $table = 'sk_federations_notifications';

    protected $fillable = [
        'user_id',
        'category',
        'title',
        'body',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
