<?php

namespace App\Modules\Deleted_SK_Officials\Models;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Shared\Models\Tenant;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletedSkOfficial extends Model
{
    protected $table = 'deleted_sk_officials';

    protected $fillable = [
        'tenant_id',
        'original_user_id',
        'barangay_id',
        'barangay_name',
        'email',
        'full_name',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'contact_number',
        'position',
        'federation_position',
        'role',
        'account_status',
        'sex',
        'date_of_birth',
        'age',
        'municipality',
        'province',
        'region',
        'term_start',
        'term_end',
        'term_status',
        'term_label',
        'email_verified_at',
        'original_created_at',
        'original_updated_at',
        'deleted_at',
        'deleted_by',
        'deleted_by_name',
        'deletion_reason',
        'archived_at',
        'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'term_start' => 'date',
            'term_end' => 'date',
            'email_verified_at' => 'datetime',
            'original_created_at' => 'datetime',
            'original_updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'archived_at' => 'datetime',
            'restored_at' => 'datetime',
            'age' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function originalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_user_id')->withTrashed();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActiveArchive($query)
    {
        return $query->whereNull('restored_at');
    }
}
