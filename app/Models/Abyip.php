<?php

namespace App\Models;

use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Abyip extends Model
{
    public const ROW_DOCUMENT = 'document';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'abyip';

    protected $fillable = [
        'status',
        'reviewed_at',
        'reviewed_by_user_id',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function scopeDocuments(Builder $query): Builder
    {
        return $query->where('row_type', self::ROW_DOCUMENT);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
