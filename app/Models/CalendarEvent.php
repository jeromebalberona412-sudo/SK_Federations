<?php

namespace App\Models;

use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    protected $table = 'calendar_events';

    protected $fillable = [
        'barangay_id',
        'user_id',
        'event_date',
        'start_time',
        'end_time',
        'title',
        'description',
        'task_type',
        'status',
        'target_audience',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForBarangay($query, ?int $barangayId)
    {
        if ($barangayId === null) {
            return $query->whereNull('barangay_id');
        }

        return $query->where('barangay_id', $barangayId);
    }

    public function scopeVisibleToFederation($query)
    {
        return $query->where('target_audience', 'SK Fed');
    }
}
