<?php

namespace App\Modules\Shared\Models;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\AuditLog\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'code',
        'municipality',
        'province',
        'region',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function barangays(): HasMany
    {
        return $this->hasMany(Barangay::class);
    }

    public function officialProfiles(): HasMany
    {
        return $this->hasMany(OfficialProfile::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(AdminActivityLog::class);
    }
}
