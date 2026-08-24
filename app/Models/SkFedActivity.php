<?php

namespace App\Models;

use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkFedActivity extends Model
{
    protected $table = 'sk_fed_activities';

    protected $fillable = [
        'barangay_id',
        'user_id',
        'action_type',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function scopeForBarangay($query, int $barangayId)
    {
        return $query->where('barangay_id', $barangayId);
    }
}
