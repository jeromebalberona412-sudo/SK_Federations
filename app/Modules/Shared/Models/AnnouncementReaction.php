<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementReaction extends Model
{
    public const TYPES = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

    protected $table = 'community_feed_reactions';

    protected $fillable = [
        'community_feed_id',
        'user_id',
        'user_type',
        'reaction_type',
    ];

    public function communityFeed(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'community_feed_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
