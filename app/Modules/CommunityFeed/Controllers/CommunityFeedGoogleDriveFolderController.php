<?php

namespace App\Modules\CommunityFeed\Controllers;

use App\Modules\CommunityFeed\Services\CommunityFeedGoogleDriveFolderSyncService;
use App\Modules\CommunityFeed\Services\GoogleDriveFolderUrlService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Announcement;
use App\Modules\Shared\Models\CommunityFeedDriveFolder;
use App\Services\ProhibitedWordsService;
use App\Services\SkFedPageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CommunityFeedGoogleDriveFolderController extends Controller
{
    public function __construct(
        private readonly CommunityFeedGoogleDriveFolderSyncService $syncService,
        private readonly GoogleDriveFolderUrlService $folderUrlService,
        private readonly CommunityFeedPostController $postController,
        private readonly ProhibitedWordsService $prohibitedWords,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $config = $this->syncService->federationFolderForUser($user);

        return response()->json([
            'configured' => $config !== null,
            'max_videos' => CommunityFeedGoogleDriveFolderSyncService::maxVideos(),
            'folder' => $config ? $this->folderPayload($config) : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folder_url' => 'required|string|max:2048',
            'sync_now' => 'sometimes|boolean',
        ]);

        $parsed = $this->folderUrlService->parseOrFail($validated['folder_url']);
        $user = Auth::user();
        $syncNow = $request->boolean('sync_now', false);

        try {
            $connected = $this->syncService->connectOnly($user, $parsed['folder_url']);

            if ($syncNow) {
                $result = $this->syncService->syncFolderConfig($connected['folder'], $user, [
                    'create_if_missing' => false,
                ]);

                return response()->json([
                    'message' => $result['message'],
                    'connected' => true,
                    'selected_count' => $result['selected_count'],
                    'created_count' => $result['created_count'],
                    'updated_count' => $result['updated_count'],
                    'deactivated_count' => $result['deactivated_count'],
                    'max_videos' => CommunityFeedGoogleDriveFolderSyncService::maxVideos(),
                    'folder' => $this->folderPayload($result['folder']),
                ]);
            }

            return response()->json([
                'message' => $connected['message'],
                'connected' => true,
                'video_count' => $connected['video_count'],
                'max_videos' => CommunityFeedGoogleDriveFolderSyncService::maxVideos(),
                'folder' => $this->folderPayload($connected['folder']),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable) {
            return response()->json(['message' => 'Unable to connect Google Drive folder.'], 422);
        }
    }

    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:announcement,event,activity,program,update',
            'title' => [
                Rule::requiredIf(fn () => $request->input('type') === 'update'),
                'nullable',
                'string',
                'max:50',
            ],
            'body' => 'required|string',
            'email_notifications_enabled' => 'sometimes|boolean',
            'folder_url' => 'nullable|string|max:2048',
            'visibility' => 'nullable|in:public,federation',
            'notify_targets' => 'nullable|array|min:1',
            'notify_targets.*' => 'in:public,federation,officials,kabataan',
        ]);

        $user = Auth::user();
        $config = $this->syncService->federationFolderForUser($user);

        if ($config === null && ! empty($validated['folder_url'])) {
            try {
                $connected = $this->syncService->connectOnly($user, (string) $validated['folder_url']);
                $config = $connected['folder'];
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        if ($config === null) {
            return response()->json([
                'message' => 'Connect a Google Drive folder first (Save / Connect Folder).',
            ], 422);
        }

        $title = isset($validated['title']) ? trim((string) $validated['title']) : null;
        if ($validated['type'] !== 'update') {
            $title = ($title !== null && $title !== '') ? $title : null;
        }
        if (is_string($title) && $title !== '') {
            try {
                $this->prohibitedWords->assertClean($title, 'title');
            } catch (ValidationException $e) {
                throw $e;
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $emailNotificationsEnabled = $request->boolean('email_notifications_enabled');
        $notifyTargets = $this->postController->normalizeNotifyTargets(
            $request->input('notify_targets'),
            $validated['visibility'] ?? null
        );
        $visibility = in_array('public', $notifyTargets, true) ? 'public' : 'federation';

        try {
            $result = $this->syncService->publishAlbumFromConfig($config, $user, [
                'body' => (string) $validated['body'],
                'type' => (string) $validated['type'],
                'title' => $title,
                'email_notifications_enabled' => $emailNotificationsEnabled,
                'visibility' => $visibility,
                'notify_targets' => $notifyTargets,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable) {
            return response()->json(['message' => 'Unable to publish Google Drive folder videos.'], 422);
        }

        $post = Announcement::with([
            'barangay',
            'comments.user',
            'comments.reactions',
            'user',
            'images',
            'videos',
            'reactions.user.officialProfile',
        ])
            ->withCount('reactions')
            ->findOrFail((int) $result['post']->id);

        $this->postController->dispatchFederationPostNotifications(
            (int) $post->id,
            (int) $user->id,
            $emailNotificationsEnabled
        );

        app(SkFedPageCache::class)->bump('community.feed');
        app(SkFedPageCache::class)->bump('archive.deleted_posts');

        $payload = $this->postController->formatPostForPage($post, (int) $user->id);
        $payload['email_notifications_queued'] = $emailNotificationsEnabled;
        $payload['message'] = $result['message'];
        $payload['folder'] = $this->folderPayload($result['folder']);

        return response()->json($payload, 201);
    }

    public function sync(Request $request): JsonResponse
    {
        $user = Auth::user();
        $config = $this->syncService->federationFolderForUser($user);

        if ($config === null) {
            return response()->json([
                'message' => 'No Google Drive folder is configured yet.',
            ], 404);
        }

        try {
            $result = $this->syncService->syncFolderConfig($config, $user, [
                'create_if_missing' => false,
            ]);

            return response()->json([
                'message' => $result['message'],
                'selected_count' => $result['selected_count'],
                'created_count' => $result['created_count'],
                'updated_count' => $result['updated_count'],
                'deactivated_count' => $result['deactivated_count'],
                'max_videos' => CommunityFeedGoogleDriveFolderSyncService::maxVideos(),
                'folder' => $this->folderPayload($result['folder']),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable) {
            return response()->json(['message' => 'Unable to sync Google Drive folder.'], 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function folderPayload(CommunityFeedDriveFolder $folder): array
    {
        return [
            'folder_id' => $folder->folder_id,
            'folder_url' => $folder->folder_url,
            'folder_name' => $folder->folder_name,
            'community_feed_id' => $folder->community_feed_id,
            'last_synced_at' => optional($folder->last_synced_at)?->toIso8601String(),
            'last_sync_status' => $folder->last_sync_status,
            'last_sync_message' => $folder->last_sync_message,
            'synced_video_count' => (int) $folder->synced_video_count,
        ];
    }
}
