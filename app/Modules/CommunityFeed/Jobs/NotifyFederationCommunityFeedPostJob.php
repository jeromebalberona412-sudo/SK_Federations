<?php

namespace App\Modules\CommunityFeed\Jobs;

use App\Modules\CommunityFeed\Services\FederationCommunityFeedBroadcastService;
use App\Modules\Shared\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyFederationCommunityFeedPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $postId,
        public readonly bool $sendEmail = false,
        public readonly bool $sendInApp = true,
    ) {}

    public function handle(FederationCommunityFeedBroadcastService $broadcastService): void
    {
        $post = Announcement::query()->find($this->postId);
        if ($post === null || ! $post->is_federation_wide) {
            return;
        }

        $authorUserId = (int) ($post->user_id ?? 0);

        if ($this->sendInApp) {
            try {
                $broadcastService->broadcastInApp($post, $authorUserId);
            } catch (Throwable $exception) {
                Log::error('[NotifyFederationCommunityFeedPostJob] In-app broadcast failed.', [
                    'community_feed_id' => $this->postId,
                    'error' => $exception->getMessage(),
                ]);
                report($exception);
            }
        }

        if (! $this->sendEmail || ! $post->email_notifications_enabled) {
            return;
        }

        try {
            $broadcastService->broadcastEmail($post);
        } catch (Throwable $exception) {
            Log::error('[NotifyFederationCommunityFeedPostJob] Email broadcast failed.', [
                'community_feed_id' => $this->postId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
