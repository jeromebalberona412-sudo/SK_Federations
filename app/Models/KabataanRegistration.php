<?php

namespace App\Models;

use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KabataanRegistration extends Model
{
    use SoftDeletes;

    protected $table = 'kabataan_registrations';

    protected $fillable = [
        'tenant_id', 'barangay_id', 'user_id', 'reviewed_by_user_id',
        'last_name', 'first_name', 'middle_name', 'suffix',
        'email', 'contact_number', 'respondent_number', 'respondent_sequence',
        'form_data', 'status',
        'evaluation_status', 'evaluation_notes',
        'submitted_at', 'email_verified_at', 'password_set_at',
        'reviewed_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'form_data' => 'array',
            'evaluation_notes' => 'array',
            'submitted_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password_set_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function getFullNameAttribute(): string
    {
        $name = trim($this->first_name.' '.$this->middle_name.' '.$this->last_name);
        if ($this->suffix) {
            $name .= ', '.$this->suffix;
        }

        return $name ?: '—';
    }
}
