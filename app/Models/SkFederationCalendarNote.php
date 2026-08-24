<?php

namespace App\Models;

use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkFederationCalendarNote extends Model
{
    protected $table = 'sk_federations_calendar';

    protected $fillable = [
        'user_id',
        'note_date',
        'title',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'note_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
