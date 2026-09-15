<?php

namespace App\Modules\CommunityFeed\Services;

use App\Modules\Deleted_Posts\Services\DeletedPostsService;
use App\Modules\Shared\Models\Announcement;
use App\Modules\Shared\Models\AnnouncementVideo;
use App\Modules\Shared\Models\CommunityFeedDriveFolder;
use App\Modules\Shared\Models\User;
use App\Services\SkFedPageCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Google Drive folder → ONE federation-wide Community Feed album post (oldest N videos).
 * Save/Connect only stores the folder. Publishing happens on Post.
 *
 * Federation folder rows use barangay_id IS NULL (user-scoped). Officials rows (if shared DB)
 * keep barangay_id set; Postgres UNIQUE(barangay_id) allows multiple NULL federation rows.
 */
class CommunityFeedGoogleDriveFolderSyncService
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @var list<string> */
    private const PREFERRED_VIDEO_MIMES = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/x-m4v',
    ];

    public function __construct(
        private readonly GoogleDriveApiClient $driveApi,
        private readonly GoogleDriveFolderUrlService $folderUrl,
        private readonly GoogleDriveVideoUrlService $fileUrl,
        private readonly DeletedPostsService $deletedPosts,
        private readonly CommunityFeedPostBodyValidator $postBodyValidator,
        private readonly SkFedPageCache $pageCache,
    ) {}

    public static function maxVideos(): int
    {
        $configured = (int) config('services.google_drive.folder_max_videos', 50);

        return $configured > 0 ? $configured : 50;
    }

    /**
     * Validate and save folder config only. Does not create a Community Feed post.
     *
     * @return array{folder: CommunityFeedDriveFolder, video_count: int, message: string}
     */
    public function connectOnly(User $user, string $folderUrl): array
    {
        $parsed = $this->folderUrl->parseOrFail($folderUrl);
        $maxVideos = self::maxVideos();

        try {
            $folderMeta = $this->driveApi->getFolder($parsed['folder_id']);
            $rawFiles = $this->driveApi->listVideoFilesInFolder($parsed['folder_id']);
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage() ?: 'Unable to access this Google Drive folder.', 0, $e);
        }

        $selected = $this->selectOldestVideos($rawFiles, $maxVideos);
        $count = count($selected);

        $previousFolderId = null;
        $existingConfig = $this->federationFolderForUser($user);
        if ($existingConfig && $existingConfig->folder_id !== $parsed['folder_id']) {
            $previousFolderId = $existingConfig->folder_id;
        }

        $message = $count === 0
            ? 'Folder connected. No valid videos found yet. Write your caption and click Post after adding videos.'
            : ($count === 1
                ? 'Folder connected. 1 video ready. Write your caption and click Post.'
                : "Folder connected. {$count} videos ready. Write your caption and click Post.");

        // Federation-scoped: one folder config per user with barangay_id null.
        $config = CommunityFeedDriveFolder::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'barangay_id' => null,
            ],
            [
                'folder_id' => $parsed['folder_id'],
                'folder_url' => $parsed['folder_url'],
                'folder_name' => $folderMeta['name'],
                'last_sync_status' => 'connected',
                'last_sync_message' => $message,
                'synced_video_count' => $count,
                'last_synced_at' => now(),
            ]
        );

        if ($previousFolderId) {
            $this->deactivateFolderVideos($previousFolderId, $user);
        }

        return [
            'folder' => $config->fresh(),
            'video_count' => $count,
            'message' => (string) $config->last_sync_message,
        ];
    }

    /**
     * @param  array{
     *     body: string,
     *     type?: string,
     *     title?: string|null,
     *     email_notifications_enabled?: bool,
     *     visibility?: string|null,
     *     notify_targets?: list<string>|null
     * }  $postOptions
     * @return array{
     *     folder: CommunityFeedDriveFolder,
     *     post: Announcement,
     *     selected_count: int,
     *     created_count: int,
     *     updated_count: int,
     *     deactivated_count: int,
     *     message: string,
     *     was_new_post: bool
     * }
     */
    public function publishAlbumFromConfig(CommunityFeedDriveFolder $config, User $actor, array $postOptions): array
    {
        $body = $this->postBodyValidator->assertValid((string) ($postOptions['body'] ?? ''));
        $type = (string) ($postOptions['type'] ?? 'update');
        $allowedTypes = ['announcement', 'event', 'activity', 'program', 'update', 'photo'];
        if (! in_array($type, $allowedTypes, true)) {
            $type = 'update';
        }
        if ($type === 'photo') {
            throw new RuntimeException('Photo posts are for images only. Choose another post type for videos.');
        }

        $title = isset($postOptions['title']) ? trim((string) $postOptions['title']) : null;
        if ($type !== 'update') {
            $title = ($title !== null && $title !== '') ? $title : null;
        }

        $result = $this->syncFolderConfig($config, $actor, [
            'create_if_missing' => true,
            'body' => $body,
            'type' => $type,
            'title' => $title,
            'email_notifications_enabled' => (bool) ($postOptions['email_notifications_enabled'] ?? false),
            'visibility' => $postOptions['visibility'] ?? null,
            'notify_targets' => $postOptions['notify_targets'] ?? null,
        ]);

        $config = $result['folder'];
        $post = Announcement::query()->find((int) $config->community_feed_id);
        if (! $post) {
            throw new RuntimeException('Unable to create the Google Drive folder post.');
        }

        return array_merge($result, [
            'post' => $post,
        ]);
    }

    /**
     * @param  array{
     *     create_if_missing?: bool,
     *     body?: string|null,
     *     type?: string|null,
     *     title?: string|null,
     *     email_notifications_enabled?: bool,
     *     visibility?: string|null,
     *     notify_targets?: list<string>|null
     * }  $options
     * @return array{
     *     folder: CommunityFeedDriveFolder,
     *     selected_count: int,
     *     created_count: int,
     *     updated_count: int,
     *     deactivated_count: int,
     *     message: string,
     *     was_new_post: bool
     * }
     */
    public function syncFolderConfig(CommunityFeedDriveFolder $config, ?User $actor = null, array $options = []): array
    {
        $actor = $actor ?: $config->user;
        if (! $actor) {
            throw new RuntimeException('Sync actor is missing.');
        }

        $createIfMissing = (bool) ($options['create_if_missing'] ?? false);
        $maxVideos = self::maxVideos();

        try {
            $this->driveApi->getFolder($config->folder_id);
            $rawFiles = $this->driveApi->listVideoFilesInFolder($config->folder_id);
            $selected = $this->selectOldestVideos($rawFiles, $maxVideos);

            if ($selected === [] && ! $config->community_feed_id && ! $createIfMissing) {
                $message = 'No valid videos were found in this Google Drive folder.';
                $this->markSyncResult($config, 'empty', $message, 0);

                return [
                    'folder' => $config->fresh(),
                    'selected_count' => 0,
                    'created_count' => 0,
                    'updated_count' => 0,
                    'deactivated_count' => 0,
                    'message' => $message,
                    'was_new_post' => false,
                ];
            }

            if ($selected !== [] && ! $config->community_feed_id && ! $createIfMissing) {
                $message = 'Folder is connected. Click Post to publish the videos.';
                $this->markSyncResult($config, 'connected', $message, count($selected));

                return [
                    'folder' => $config->fresh(),
                    'selected_count' => count($selected),
                    'created_count' => 0,
                    'updated_count' => 0,
                    'deactivated_count' => 0,
                    'message' => $message,
                    'was_new_post' => false,
                ];
            }

            $result = $this->reconcileSelected($config, $actor, $selected, $options);

            $count = (int) $result['selected_count'];
            $message = $count === 0
                ? 'No valid videos were found in this Google Drive folder.'
                : ($count === 1
                    ? 'Published 1 video in one Community Feed post.'
                    : "Published {$count} videos in one Community Feed post.");
            $this->markSyncResult($config, $count === 0 ? 'empty' : 'ok', $message, $count);
            $this->pageCache->bump('community.feed');
            $this->pageCache->bump('archive.deleted_posts');

            return array_merge($result, [
                'folder' => $config->fresh(),
                'message' => $message,
            ]);
        } catch (Throwable $e) {
            $message = $e->getMessage() ?: 'Google Drive folder sync failed.';
            $this->markSyncResult($config, 'error', $message, (int) $config->synced_video_count);
            throw new RuntimeException($message, 0, $e);
        }
    }

    public function federationFolderForUser(User $user): ?CommunityFeedDriveFolder
    {
        return CommunityFeedDriveFolder::query()
            ->where('user_id', $user->id)
            ->whereNull('barangay_id')
            ->first();
    }

    /**
     * @param  list<array{id: string, name: string, mimeType: string, createdTime: string, size: int|null, webViewLink: string|null}>  $files
     * @return list<array{id: string, name: string, mimeType: string, createdTime: string, size: int|null, webViewLink: string|null}>
     */
    public function selectOldestVideos(array $files, ?int $limit = null): array
    {
        $limit = max(0, $limit ?? self::maxVideos());
        $videos = [];

        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }
            $mime = strtolower(trim((string) ($file['mimeType'] ?? '')));
            if (! $this->isAllowedVideoMime($mime)) {
                continue;
            }
            $id = trim((string) ($file['id'] ?? ''));
            $created = trim((string) ($file['createdTime'] ?? ''));
            if ($id === '' || $created === '') {
                continue;
            }
            $videos[] = [
                'id' => $id,
                'name' => (string) ($file['name'] ?? $id),
                'mimeType' => $mime,
                'createdTime' => $created,
                'size' => isset($file['size']) ? (int) $file['size'] : null,
                'webViewLink' => isset($file['webViewLink']) ? (string) $file['webViewLink'] : null,
            ];
        }

        usort($videos, static function (array $a, array $b): int {
            $timeCmp = strcmp($a['createdTime'], $b['createdTime']);
            if ($timeCmp !== 0) {
                return $timeCmp;
            }

            return strcmp($a['id'], $b['id']);
        });

        return array_slice($videos, 0, $limit);
    }

    public function isAllowedVideoMime(string $mimeType): bool
    {
        $mime = strtolower(trim($mimeType));
        if ($mime === '' || ! str_starts_with($mime, 'video/')) {
            return false;
        }

        if (in_array($mime, self::PREFERRED_VIDEO_MIMES, true)) {
            return true;
        }

        return true;
    }

    /**
     * @param  list<array{id: string, name: string, mimeType: string, createdTime: string, size: int|null, webViewLink: string|null}>  $selected
     * @param  array{
     *     create_if_missing?: bool,
     *     body?: string|null,
     *     type?: string|null,
     *     title?: string|null,
     *     email_notifications_enabled?: bool,
     *     visibility?: string|null,
     *     notify_targets?: list<string>|null
     * }  $options
     * @return array{selected_count: int, created_count: int, updated_count: int, deactivated_count: int, was_new_post: bool}
     */
    private function reconcileSelected(CommunityFeedDriveFolder $config, User $actor, array $selected, array $options = []): array
    {
        $created = 0;
        $updated = 0;
        $deactivated = 0;
        $selectedIds = array_map(static fn (array $row): string => $row['id'], $selected);
        $createIfMissing = (bool) ($options['create_if_missing'] ?? false);
        $wasNewPost = false;

        DB::transaction(function () use (
            $config,
            $actor,
            $selected,
            $selectedIds,
            $options,
            $createIfMissing,
            &$created,
            &$updated,
            &$deactivated,
            &$wasNewPost
        ) {
            $albumPost = $this->resolveAlbumPost($config, $actor, $options, $createIfMissing, $wasNewPost);
            if (! $albumPost) {
                return;
            }

            $this->archiveLegacyPerVideoPosts($config, $actor, (int) $albumPost->id);

            if ($selected === []) {
                $stale = AnnouncementVideo::query()
                    ->where('community_feed_id', $albumPost->id)
                    ->where('status', self::STATUS_ACTIVE)
                    ->get();
                foreach ($stale as $video) {
                    $video->forceFill(['status' => self::STATUS_INACTIVE])->save();
                    $deactivated++;
                }
                if (! (bool) $albumPost->is_archived) {
                    $this->deletedPosts->archive($albumPost, $actor);
                }
                $config->forceFill(['community_feed_id' => $albumPost->id])->save();

                return;
            }

            if ((bool) $albumPost->is_archived) {
                DB::table('community_feeds')->where('id', $albumPost->id)->update([
                    'is_archived' => DB::raw('false'),
                    'archived_at' => null,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
                $albumPost->refresh();
            }

            $body = trim((string) ($options['body'] ?? ''));
            if ($body === '') {
                $body = trim((string) $albumPost->body);
            }
            if ($body === '') {
                $body = 'Google Drive videos';
            }

            $type = (string) ($options['type'] ?? $albumPost->type ?: 'update');
            if ($type === 'photo') {
                $type = 'update';
            }

            $title = array_key_exists('title', $options)
                ? (($options['title'] !== null && $options['title'] !== '') ? (string) $options['title'] : null)
                : $albumPost->title;

            $fill = [
                'body' => $body,
                'type' => $type,
                'title' => $title,
                'link_url' => null,
                'user_id' => $actor->id,
                'barangay_id' => null,
            ];

            if (array_key_exists('email_notifications_enabled', $options)) {
                $fill['email_notifications_enabled'] = DB::raw($options['email_notifications_enabled'] ? 'true' : 'false');
            }

            if (array_key_exists('notify_targets', $options) && is_array($options['notify_targets'])) {
                $fill['notify_targets'] = $options['notify_targets'];
            }
            if (array_key_exists('visibility', $options) && is_string($options['visibility']) && $options['visibility'] !== '') {
                $fill['visibility'] = $options['visibility'];
            }

            $isFederationWide = true;
            if (config('database.connections.'.config('database.default').'.driver') === 'pgsql') {
                $isFederationWide = DB::raw('true');
            }
            $fill['is_federation_wide'] = $isFederationWide;

            $albumPost->forceFill($fill)->save();

            foreach ($selected as $index => $file) {
                $driveCreated = Carbon::parse($file['createdTime']);
                $openUrl = $this->fileUrl->parse(
                    $file['webViewLink'] ?: ('https://drive.google.com/file/d/'.$file['id'].'/view')
                )['media_url'] ?? ('https://drive.google.com/file/d/'.$file['id'].'/view');

                $existingVideo = AnnouncementVideo::query()
                    ->where('google_drive_file_id', $file['id'])
                    ->first();

                $payload = [
                    'community_feed_id' => $albumPost->id,
                    'provider' => AnnouncementVideo::PROVIDER_GOOGLE_DRIVE,
                    'video_url' => $openUrl,
                    'public_id' => null,
                    'google_drive_file_id' => $file['id'],
                    'google_drive_folder_id' => $config->folder_id,
                    'name' => $file['name'],
                    'mime_type' => $file['mimeType'],
                    'bytes' => $file['size'],
                    'drive_created_at' => $driveCreated,
                    'web_view_link' => $openUrl,
                    'status' => self::STATUS_ACTIVE,
                    'sort_order' => $index,
                ];

                if ($existingVideo) {
                    $existingVideo->forceFill($payload)->save();
                    $updated++;
                } else {
                    AnnouncementVideo::create(array_merge($payload, [
                        'created_at' => now(),
                    ]));
                    $created++;
                }
            }

            $staleQuery = AnnouncementVideo::query()
                ->where('provider', AnnouncementVideo::PROVIDER_GOOGLE_DRIVE)
                ->where(function ($q) use ($config, $albumPost) {
                    $q->where('google_drive_folder_id', $config->folder_id)
                        ->orWhere('community_feed_id', $albumPost->id);
                })
                ->where('status', self::STATUS_ACTIVE);

            if ($selectedIds !== []) {
                $staleQuery->whereNotIn('google_drive_file_id', $selectedIds);
            }

            foreach ($staleQuery->get() as $video) {
                $video->forceFill(['status' => self::STATUS_INACTIVE])->save();
                $deactivated++;
            }

            $config->forceFill(['community_feed_id' => $albumPost->id])->save();
        });

        return [
            'selected_count' => count($selected),
            'created_count' => $created,
            'updated_count' => $updated,
            'deactivated_count' => $deactivated,
            'was_new_post' => $wasNewPost,
        ];
    }

    /**
     * @param  array{
     *     body?: string|null,
     *     type?: string|null,
     *     title?: string|null,
     *     email_notifications_enabled?: bool,
     *     visibility?: string|null,
     *     notify_targets?: list<string>|null
     * }  $options
     */
    private function resolveAlbumPost(
        CommunityFeedDriveFolder $config,
        User $actor,
        array $options,
        bool $createIfMissing,
        bool &$wasNewPost
    ): ?Announcement {
        if ($config->community_feed_id) {
            $existing = Announcement::query()->find($config->community_feed_id);
            if ($existing) {
                return $existing;
            }
        }

        $fromVideos = AnnouncementVideo::query()
            ->where('google_drive_folder_id', $config->folder_id)
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy('community_feed_id')
            ->value('community_feed_id');

        if ($fromVideos) {
            $post = Announcement::query()->find((int) $fromVideos);
            if ($post) {
                return $post;
            }
        }

        if (! $createIfMissing) {
            return null;
        }

        $body = trim((string) ($options['body'] ?? ''));
        if ($body === '') {
            throw new RuntimeException('Please write a caption before posting.');
        }

        $type = (string) ($options['type'] ?? 'update');
        if ($type === 'photo') {
            $type = 'update';
        }
        $title = isset($options['title']) && $options['title'] !== ''
            ? (string) $options['title']
            : null;
        $emailNotificationsEnabled = (bool) ($options['email_notifications_enabled'] ?? false);
        $notifyTargets = is_array($options['notify_targets'] ?? null)
            ? $options['notify_targets']
            : Announcement::defaultNotifyTargetsForVisibility($options['visibility'] ?? 'public');
        $visibility = in_array('public', $notifyTargets, true) ? 'public' : 'federation';

        $isFederationWide = true;
        if (config('database.connections.'.config('database.default').'.driver') === 'pgsql') {
            $isFederationWide = DB::raw('true');
        }

        $post = Announcement::create([
            'user_id' => $actor->id,
            'barangay_id' => null,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link_url' => null,
            'email_notifications_enabled' => DB::raw($emailNotificationsEnabled ? 'true' : 'false'),
            'is_federation_wide' => $isFederationWide,
            'is_archived' => DB::raw('false'),
            'visibility' => $visibility,
            'notify_targets' => $notifyTargets,
        ]);
        $wasNewPost = true;

        return $post;
    }

    private function archiveLegacyPerVideoPosts(CommunityFeedDriveFolder $config, User $actor, int $albumPostId): void
    {
        $postIds = AnnouncementVideo::query()
            ->where('google_drive_folder_id', $config->folder_id)
            ->where('community_feed_id', '!=', $albumPostId)
            ->distinct()
            ->pluck('community_feed_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->all();

        foreach ($postIds as $postId) {
            $post = Announcement::query()->active()->find($postId);
            if ($post) {
                $this->deletedPosts->archive($post, $actor);
            }
        }
    }

    private function markSyncResult(
        CommunityFeedDriveFolder $config,
        string $status,
        string $message,
        int $count
    ): void {
        $config->forceFill([
            'last_synced_at' => now(),
            'last_sync_status' => $status,
            'last_sync_message' => $message,
            'synced_video_count' => max(0, min(self::maxVideos(), $count)),
        ])->save();
    }

    private function deactivateFolderVideos(string $folderId, User $actor): void
    {
        $videos = AnnouncementVideo::query()
            ->where('provider', AnnouncementVideo::PROVIDER_GOOGLE_DRIVE)
            ->where('google_drive_folder_id', $folderId)
            ->where('status', self::STATUS_ACTIVE)
            ->get();

        $postIds = $videos->pluck('community_feed_id')->unique()->filter();

        foreach ($videos as $video) {
            $video->forceFill(['status' => self::STATUS_INACTIVE])->save();
        }

        foreach ($postIds as $postId) {
            $post = Announcement::query()->active()->find((int) $postId);
            if ($post) {
                $this->deletedPosts->archive($post, $actor);
            }
        }
    }
}
