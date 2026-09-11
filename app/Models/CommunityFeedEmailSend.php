<?php

namespace App\Models;

use App\Modules\Shared\Models\Announcement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityFeedEmailSend extends Model
{
    protected $table = 'community_feed_email_sends';

    protected $fillable = [
        'community_feed_id',
        'recipient_email',
        'kabataan_registration_id',
        'user_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function communityFeed(): BelongsTo
    {
        return $this->belongsTo(Announcement::class, 'community_feed_id');
    }
}
