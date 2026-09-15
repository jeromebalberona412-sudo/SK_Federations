<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityFeedDriveFolder extends Model
{
    protected $table = 'community_feed_drive_folders';

    protected $fillable = [
        'barangay_id',
        'user_id',
        'community_feed_id',
        'folder_id',
        'folder_url',
        'folder_name',
        'last_synced_at',
        'last_sync_status',
        'last_sync_message',
        'synced_video_count',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'synced_video_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function communityFeed(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'community_feed_id');
    }
}
