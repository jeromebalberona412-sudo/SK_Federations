<?php

namespace App\Modules\BarangayLogos\Models;

use App\Modules\Profile\Models\Barangay;
use App\Modules\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangayLogo extends Model
{
    protected $fillable = [
        'barangay_id',
        'tenant_id',
        'uploaded_by',
        'cloudinary_public_id',
        'cloudinary_version',
        'url',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
