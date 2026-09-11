<?php

namespace App\Models;

use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkFederationCalendarNote extends Model
{
    use SoftDeletes;

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
