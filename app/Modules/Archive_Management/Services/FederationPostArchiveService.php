<?php

namespace App\Modules\Archive_Management\Services;

use App\Modules\CommunityFeed\Services\CloudinaryService;
use App\Modules\Shared\Models\Announcement;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FederationPostArchiveService
{
    public const RETENTION_DAYS = 30;

    public function __construct(private readonly CloudinaryService $cloudinary) {}

    public function archive(Announcement $post, User $user): Announcement
    {
        $now = now();

        DB::table('community_feeds')
            ->where('id', $post->id)
            ->update([
                'is_archived' => DB::raw('true'),
                'archived_at' => $now,
                'deleted_at' => null,
                'updated_at' => $now,
            ]);

        return $post->fresh(['user', 'barangay', 'images']);
    }

    public function restore(Announcement $post): Announcement
    {
        $now = now();

        DB::table('community_feeds')
            ->where('id', $post->id)
            ->update([
                'is_archived' => DB::raw('false'),
                'archived_at' => null,
                'deleted_at' => null,
                'updated_at' => $now,
            ]);

        return $post->fresh(['user', 'barangay', 'images']);
    }

    public function permanentlyDelete(Announcement $post): void
    {
        DB::transaction(function () use ($post) {
            $post->loadMissing('images');
            $post->images()->delete();
            $post->comments()->delete();
            $post->reactions()->delete();
            $post->delete();
        });
    }

    public function daysRemaining(?Carbon $archivedAt): int
    {
        if ($archivedAt === null) {
            return 0;
        }

        $expiresAt = $archivedAt->copy()->addDays(self::RETENTION_DAYS);

        return max(0, (int) now()->diffInDays($expiresAt, false));
    }

    public function daysRemainingTier(int $daysRemaining): string
    {
        if ($daysRemaining <= 3) {
            return 'red';
        }

        if ($daysRemaining <= 7) {
            return 'orange';
        }

        return 'green';
    }
}
