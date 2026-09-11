<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementVideo extends Model
{
    public $timestamps = false;

    protected $table = 'community_feed_videos';

    public const PROVIDER_CLOUDINARY = 'cloudinary';

    public const PROVIDER_GOOGLE_DRIVE = 'google_drive';

    protected $fillable = [
        'community_feed_id',
        'provider',
        'video_url',
        'public_id',
        'google_drive_file_id',
        'google_drive_folder_id',
        'name',
        'mime_type',
        'bytes',
        'drive_created_at',
        'web_view_link',
        'status',
        'sort_order',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'drive_created_at' => 'datetime',
        ];
    }

    public function communityFeed(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'community_feed_id');
    }
}
