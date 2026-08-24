<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportManagement extends Model
{
    use SoftDeletes;

    protected $table = 'report_management';

    protected $fillable = [
        'tenant_id',
        'barangay_id',
        'user_id',
        'program_code',
        'program_name',
        'activity_name',
        'file_name',
        'file_path',
        'file_mime',
        'file_size',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Profile\Models\Barangay::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Shared\Models\User::class);
    }
}
