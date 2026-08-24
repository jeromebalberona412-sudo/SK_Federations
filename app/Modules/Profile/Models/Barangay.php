<?php

namespace App\Modules\Profile\Models;

use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    protected $table = 'barangays';

    protected $fillable = [
        'tenant_id',
        'name',
        'municipality',
        'province',
        'region',
    ];
}
