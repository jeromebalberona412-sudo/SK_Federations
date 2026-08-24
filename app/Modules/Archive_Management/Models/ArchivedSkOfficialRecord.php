<?php

namespace App\Modules\Archive_Management\Models;

use App\Modules\Accounts\Models\Barangay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivedSkOfficialRecord extends Model
{
    protected $table = 'archived_sk_official_records';

    protected $fillable = [
        'user_id',
        'official_profile_id',
        'official_term_id',
        'tenant_id',
        'barangay_id',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'sex',
        'date_of_birth',
        'age',
        'contact_number',
        'position',
        'municipality',
        'province',
        'region',
        'email',
        'term_start',
        'term_end',
        'term_status',
        'archived_at',
        'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'term_start' => 'date',
            'term_end' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}
