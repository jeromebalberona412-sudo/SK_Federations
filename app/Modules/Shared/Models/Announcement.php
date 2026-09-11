<?php

namespace App\Modules\Shared\Models;

use App\Modules\Profile\Models\Barangay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'email_notifications_enabled',
        'share_token',
        'is_federation_wide',
        'visibility',
        'notify_targets',
        'is_archived',
        'archived_at',
        'deleted_at',
    ];

    /** @var list<string> */
    public const NOTIFY_TARGET_OPTIONS = ['public', 'federation', 'officials', 'kabataan'];

    protected $casts = [
        'email_notifications_enabled' => 'boolean',
        'is_federation_wide' => 'boolean',
        'is_archived' => 'boolean',
        'notify_targets' => 'array',
        'archived_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function isPublicAudience(): bool
    {
        return ($this->visibility ?: 'public') === 'public';
    }

    public function isFederationAudience(): bool
    {
        return ($this->visibility ?: 'public') === 'federation';
    }

    /**
     * @return list<string>
     */
    public function notifyTargets(): array
    {
        $raw = $this->notify_targets;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw) || $raw === []) {
            return $this->isPublicAudience()
                ? ['public', 'officials', 'kabataan']
                : ['federation', 'officials', 'kabataan'];
        }

        $normalized = [];
        foreach ($raw as $target) {
            $key = strtolower(trim((string) $target));
            if (in_array($key, self::NOTIFY_TARGET_OPTIONS, true) && ! in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        return $normalized !== []
            ? $normalized
            : ($this->isPublicAudience()
                ? ['public', 'officials', 'kabataan']
                : ['federation', 'officials', 'kabataan']);
    }

    public function shouldNotifyOfficials(): bool
    {
        return in_array('officials', $this->notifyTargets(), true);
    }

    public function shouldNotifyKabataan(): bool
    {
        return in_array('kabataan', $this->notifyTargets(), true);
    }

    public function shouldNotifyFederationPortal(): bool
    {
        $targets = $this->notifyTargets();

        return in_array('public', $targets, true) || in_array('federation', $targets, true);
    }

    public function isShareable(): bool
    {
        return ! (bool) $this->is_archived
            && filled($this->share_token)
            && $this->isPublicAudience();
    }

    protected static function booted(): void
    {
        static::creating(function (Announcement $post): void {
            if (! filled($post->share_token)) {
                $post->share_token = (string) Str::ulid();
            }
        });
    }

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

    public function videos(): HasMany
    {
        return $this->hasMany(AnnouncementVideo::class, 'community_feed_id')->orderBy('sort_order');
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
