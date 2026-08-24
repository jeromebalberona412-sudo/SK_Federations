<?php

namespace App\Modules\Shared\Models;

use App\Modules\Profile\Models\Barangay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $table = 'community_feeds';

    protected $fillable = [
        'user_id',
        'barangay_id',
        'type',
        'title',
        'body',
        'link_url',
        'is_federation_wide',
        'is_archived',
        'archived_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_federation_wide' => 'boolean',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(AnnouncementReaction::class, 'community_feed_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class, 'community_feed_id')->orderBy('created_at');
    }

    public function images(): HasMany
    {
        return $this->hasMany(AnnouncementImage::class, 'community_feed_id')->orderBy('sort_order');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereRaw('"is_archived" = true');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereRaw('"is_archived" = false');
    }

    public function scopeFederationWide(Builder $query): Builder
    {
        return $query->whereRaw('"is_federation_wide" = true');
    }
}
