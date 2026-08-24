<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnouncementComment extends Model
{
    protected $table = 'community_feed_comments';

    protected $fillable = [
        'community_feed_id',
        'parent_id',
        'user_id',
        'user_type',
        'author_name',
        'body',
    ];

    public function communityFeed(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'community_feed_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(AnnouncementCommentReaction::class, 'comment_id');
    }
}
