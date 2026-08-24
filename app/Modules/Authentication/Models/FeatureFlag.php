<?php

namespace App\Modules\Authentication\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $table = 'sk_fed_feature_flags';

    protected $fillable = [
        'flag_key',
        'enabled',
        'description',
        'rollout_percentage',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'rollout_percentage' => 'integer',
            'metadata' => 'array',
        ];
    }
}
