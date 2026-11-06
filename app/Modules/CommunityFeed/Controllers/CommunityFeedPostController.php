<?php

namespace App\Modules\CommunityFeed\Controllers;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Accounts\Support\PortalAppUrl;
use App\Modules\CommunityFeed\Services\CloudinaryService;
use App\Modules\CommunityFeed\Services\CommunityFeedPostBodyValidator;
use App\Modules\CommunityFeed\Services\CommunityFeedService;
use App\Modules\CommunityFeed\Services\FederationCommunityFeedBroadcastService;
use App\Modules\CommunityFeed\Services\GoogleDriveVideoUrlService;
use App\Modules\Deleted_Posts\Services\DeletedPostsService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Announcement;
use App\Modules\Shared\Models\AnnouncementComment;
use App\Modules\Shared\Models\AnnouncementCommentReaction;
use App\Modules\Shared\Models\AnnouncementImage;
use App\Modules\Shared\Models\AnnouncementReaction;
use App\Modules\Shared\Models\AnnouncementVideo;
use App\Modules\Shared\Models\User;
use App\Support\OpaqueUrlId;
use App\Services\CommunityFeedAvatarService;
use App\Services\FeedCommentRateLimiter;
use App\Services\ProhibitedWordsService;
use App\Services\SkFederationsNotificationService;
use App\Services\SkFedPageCache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CommunityFeedPostController extends Controller
{
    private const MAX_IMAGES = 20;

    private const ALLOWED_VIDEO_MIMES = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/x-m4v',
    ];

    public function __construct(
        private readonly CloudinaryService $cloudinary,
        private readonly SkFederationsNotificationService $notificationService,
        private readonly CommunityFeedAvatarService $avatarService,
        private readonly CommunityFeedPostBodyValidator $postBodyValidator,
        private readonly ProhibitedWordsService $prohibitedWords,
        private readonly GoogleDriveVideoUrlService $googleDriveVideoUrl,
    ) {}

    // GET /api/community-feed?filter=all&page=1
    public function feed(Request $request): JsonResponse
    {
        $user = Auth::user();
        $pageCache = app(SkFedPageCache::class);
        $perPage = min(100, max(1, (int) $request->get('per_page', 100)));
        $filter = (string) ($request->filter ?: 'all');
        $search = trim((string) $request->get('search', ''));
        $barangayId = $request->filled('barangay_id')
            ? OpaqueUrlId::decode((string) $request->query('barangay_id'))
            : null;
        if ($barangayId !== null) {
            abort_unless(
                Barangay::query()
                    ->where('tenant_id', $user->tenant_id)
                    ->whereKey($barangayId)
                    ->exists(),
                404
            );
        }
        $federationOnly = $request->boolean('federation_only');
        $year = (int) $request->query('year', 0);
        if ($year < 2000 || $year > 2100) {
            $year = 0;
        }

        $payload = $pageCache->remember('community.feed', function () use ($user, $perPage, $filter, $search, $barangayId, $federationOnly, $year) {
            $query = Announcement::with([
                'barangay',
                'comments.user',
                'comments.reactions',
                'user',
                'images',
                'videos',
                'reactions.user.officialProfile',
            ])
                ->withCount('reactions')
                ->active()
                ->visibleToTenant((int) $user->tenant_id);

            $query->orderByDesc('created_at');

            if ($filter !== 'all') {
                $query->where('type', $filter);
            }

            if ($federationOnly) {
                $query->whereRaw('"is_federation_wide" = true');
            } elseif ($barangayId) {
                $query->where('barangay_id', $barangayId)
                    ->whereRaw('"is_federation_wide" = false');
            }

            if ($year > 0) {
                $query->whereYear('created_at', $year);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%'.$search.'%')
                        ->orWhere('body', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', '%'.$search.'%'));
                });
            }

            $posts = $query->paginate($perPage);

            return [
                'data' => collect($posts->items())->map(fn ($p) => $this->formatPost($p, $user->id))->values()->all(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
                'user_id' => $user->id,
                'year' => $year > 0 ? $year : null,
            ];
        }, [
            'user' => $user->id,
            'page' => (int) $request->get('page', 1),
            'per_page' => $perPage,
            'filter' => $filter,
            'search' => $search,
            'barangay_id' => $barangayId,
            'federation_only' => $federationOnly ? 1 : 0,
            'year' => $year,
        ], 20);

        return response()->json($payload)->header('Cache-Control', 'private, max-age=10');
    }

    public function formatPostForPage(Announcement $post, int $userId): array
    {
        return $this->formatPost($post, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatPublicPost(Announcement $post): array
    {
        $authorName = Str::limit(
            $post->is_federation_wide
                ? 'SK Federation'
                : ('SK Brgy. '.($post->barangay?->name ?? 'Barangay')),
            50,
            '...'
        );

        $imageRecords = $post->relationLoaded('images') ? $post->images : collect();
        $images = $imageRecords
            ->map(fn ($img) => $this->cloudinary->normalizeUrl($img->image_url))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $videoItems = $this->formatVideos($post);
        $primaryDrive = collect($videoItems)->first(
            fn ($v) => ($v['provider'] ?? null) === AnnouncementVideo::PROVIDER_GOOGLE_DRIVE
        );

        return [
            'type' => $post->type,
            'title' => $post->title,
            'body' => $post->body,
            'images' => $images,
            'videos' => $videoItems,
            'link_url' => $post->link_url,
            'google_drive_video' => $primaryDrive ?: $this->googleDriveVideoUrl->present($post->link_url),
            'author_name' => $authorName,
            'barangay_name' => $post->barangay?->name,
            'barangay_logo_url' => $this->avatarService->barangayLogo((int) $post->barangay_id),
            'time' => $post->created_at?->diffForHumans(),
            'posted_at' => $post->created_at?->timezone(config('app.timezone'))->format('F j, Y g:i A'),
        ];
    }

    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $pageCache = app(SkFedPageCache::class);

        $payload = $pageCache->remember('community.feed', function () use ($id, $user) {
            $post = Announcement::query()
                ->active()
                ->visibleToTenant((int) $user->tenant_id)
                ->with([
                    'barangay',
                    'comments.user',
                    'comments.reactions',
                    'user',
                    'images',
                    'videos',
                    'reactions.user.officialProfile',
                ])
                ->withCount('reactions')
                ->findOrFail($id);

            return $this->formatPost($post, $user->id);
        }, [
            'show' => $id,
            'user' => (int) $user->id,
        ], 8);

        return response()->json($payload)->header('Cache-Control', 'private, max-age=5');
    }

    // POST /api/community-feed
    public function store(Request $request): JsonResponse
    {
        $this->normalizeEmptyLinkUrl($request);

        $validated = $request->validate([
            'type' => 'required|in:announcement,event,activity,program,update,photo',
            'title' => [
                Rule::requiredIf(fn () => $request->input('type') === 'update'),
                'nullable',
                'string',
                'max:50',
            ],
            'body' => 'nullable|string',
            'link_url' => 'nullable|string|max:2048',
            'link_kind' => 'nullable|in:link,google_drive_video',
            'images' => 'nullable|array|max:'.self::MAX_IMAGES,
            'images.*' => 'image|max:5120',
            'videos' => 'nullable|array|max:'.$this->maxVideoCount(),
            'videos.*' => 'file|mimetypes:'.implode(',', self::ALLOWED_VIDEO_MIMES).'|max:'.$this->maxVideoKilobytes(),
            'uploaded_videos' => 'nullable|array|max:'.$this->maxVideoCount(),
            'uploaded_videos.*.public_id' => 'required|string|max:512',
            'uploaded_videos.*.video_url' => 'required|string|max:2048',
            'uploaded_videos.*.bytes' => 'nullable|integer|min:0',
            'uploaded_videos.*.mime_type' => 'nullable|string|max:100',
            'email_notifications_enabled' => 'sometimes|boolean',
            'visibility' => 'nullable|in:public,federation',
            'notify_targets' => 'nullable|array|min:1',
            'notify_targets.*' => 'in:public,federation,officials,kabataan',
        ]);

        $hasMedia = $request->hasFile('images')
            || $request->hasFile('videos')
            || ! empty($validated['uploaded_videos'] ?? [])
            || (($validated['link_kind'] ?? null) === 'google_drive_video' && filled($validated['link_url'] ?? null));
        $rawBody = (string) ($validated['body'] ?? '');
        $body = (trim($rawBody) === '' && $hasMedia)
            ? ''
            : $this->postBodyValidator->assertValid($rawBody);
        $linkUrl = $this->googleDriveVideoUrl->normalizeForStorage(
            $validated['link_url'] ?? null,
            ($validated['link_kind'] ?? null) === 'google_drive_video'
        );

        if (($validated['type'] ?? '') === 'photo') {
            $hasVideos = $request->hasFile('videos')
                || ! empty($validated['uploaded_videos'] ?? [])
                || (($validated['link_kind'] ?? null) === 'google_drive_video');
            if ($hasVideos) {
                return response()->json([
                    'message' => 'Photo posts are for images only. Videos are not allowed.',
                ], 422);
            }
            $linkUrl = null;
        }

        $user = Auth::user();
        abort_unless($user !== null && (int) $user->tenant_id > 0, 403, 'Your Federation account is not assigned to a tenant.');
        $title = isset($validated['title']) ? trim((string) $validated['title']) : null;
        if ($validated['type'] !== 'update') {
            $title = ($title !== null && $title !== '') ? $title : null;
        }
        if (is_string($title) && $title !== '') {
            try {
                $this->prohibitedWords->assertClean($title, 'title');
            } catch (ValidationException $e) {
                throw $e;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $isFederationWide = true;
        if (config('database.connections.'.config('database.default').'.driver') === 'pgsql') {
            $isFederationWide = DB::raw('true');
        }

        $emailNotificationsEnabled = $request->boolean('email_notifications_enabled');
        $notifyTargets = $this->normalizeNotifyTargets(
            $request->input('notify_targets'),
            $validated['visibility'] ?? null
        );
        $visibility = in_array('public', $notifyTargets, true) ? 'public' : 'federation';

        $idempotencyKey = trim((string) (
            $request->header('Idempotency-Key')
            ?: $request->header('X-Idempotency-Key')
            ?: ''
        ));
        $cacheKey = $idempotencyKey !== ''
            ? 'community-feed:create:'.$user->id.':'.hash('sha256', $idempotencyKey)
            : null;
        $idempotencyLock = $cacheKey ? Cache::lock($cacheKey.':lock', 120) : null;

        try {
            if ($idempotencyLock) {
                $idempotencyLock->block(8);
            }

            if ($cacheKey && ($cachedPostId = Cache::get($cacheKey))) {
                $cachedPost = Announcement::with([
                    'barangay',
                    'comments.user',
                    'comments.reactions',
                    'user',
                    'images',
                    'videos',
                    'reactions.user.officialProfile',
                ])
                    ->withCount('reactions')
                    ->find($cachedPostId);

                if ($cachedPost) {
                    $payload = $this->formatPost($cachedPost, $user->id);
                    $payload['email_notifications_queued'] = (bool) $cachedPost->email_notifications_enabled;
                    $payload['message'] = 'Federation post published successfully.';

                    return response()->json($payload);
                }
            }

            $post = Announcement::create([
                'type' => $validated['type'],
                'title' => $title,
                'body' => $body,
                'link_url' => $linkUrl,
                'email_notifications_enabled' => DB::raw($emailNotificationsEnabled ? 'true' : 'false'),
                'user_id' => $user->id,
                'barangay_id' => null,
                'is_federation_wide' => $isFederationWide,
                'visibility' => $visibility,
                'notify_targets' => $notifyTargets,
            ]);

            if ($cacheKey) {
                Cache::put($cacheKey, $post->id, now()->addMinutes(10));
            }

            $post->email_notifications_enabled = $emailNotificationsEnabled;

            $this->storePostImages($post, $request);
            $this->storePostVideos($post, $request, $linkUrl, ($validated['link_kind'] ?? null) === 'google_drive_video');
            $this->storeUploadedVideoMetadata($post, (array) ($validated['uploaded_videos'] ?? []));

            $fresh = Announcement::with([
                'barangay',
                'comments.user',
                'comments.reactions',
                'user',
                'images',
                'videos',
                'reactions.user.officialProfile',
            ])
                ->withCount('reactions')
                ->findOrFail($post->id);

            $this->dispatchFederationPostNotifications((int) $fresh->id, (int) $user->id, $emailNotificationsEnabled);

            app(SkFedPageCache::class)->bump('community.feed');
            app(SkFedPageCache::class)->bump('archive.deleted_posts');

            $payload = $this->formatPost($fresh, $user->id);
            $payload['email_notifications_queued'] = $emailNotificationsEnabled;
            $payload['message'] = $emailNotificationsEnabled
                ? 'Federation post published. Email notifications have been queued for selected targets.'
                : 'Federation post published successfully.';

            return response()->json($payload, 201);
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Your previous post request is still processing. Please wait a moment.',
            ], 409);
        } finally {
            $idempotencyLock?->release();
        }
    }

    // PUT /api/community-feed/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $post = Announcement::where('id', $id)
            ->where('user_id', Auth::id())
            ->whereRaw('"is_federation_wide" = true')
            ->firstOrFail();

        $this->normalizeEmptyLinkUrl($request);

        $validated = $request->validate([
            'type' => 'sometimes|in:announcement,event,activity,program,update,photo',
            'title' => [
                Rule::requiredIf(fn () => ($request->input('type') ?? $post->type) === 'update'),
                'nullable',
                'string',
                'max:50',
            ],
            'body' => 'sometimes|string',
            'link_url' => 'nullable|string|max:2048',
            'link_kind' => 'nullable|in:link,google_drive_video',
            'images' => 'nullable|array|max:'.self::MAX_IMAGES,
            'images.*' => 'image|max:5120',
            'videos' => 'nullable|array|max:'.$this->maxVideoCount(),
            'videos.*' => 'file|mimetypes:'.implode(',', self::ALLOWED_VIDEO_MIMES).'|max:'.$this->maxVideoKilobytes(),
            'uploaded_videos' => 'nullable|array|max:'.$this->maxVideoCount(),
            'uploaded_videos.*.public_id' => 'required|string|max:512',
            'uploaded_videos.*.video_url' => 'required|string|max:2048',
            'uploaded_videos.*.bytes' => 'nullable|integer|min:0',
            'uploaded_videos.*.mime_type' => 'nullable|string|max:100',
            'removed_image_ids' => 'nullable|array',
            'removed_image_ids.*' => 'integer',
            'removed_video_ids' => 'nullable|array',
            'removed_video_ids.*' => 'integer',
            'clear_videos' => 'sometimes|boolean',
            'visibility' => 'nullable|in:public,federation',
            'notify_targets' => 'nullable|array|min:1',
            'notify_targets.*' => 'in:public,federation,officials,kabataan',
        ]);

        if ($request->has('notify_targets') || $request->has('visibility')) {
            $notifyTargets = $this->normalizeNotifyTargets(
                $request->input('notify_targets', $post->notify_targets),
                $validated['visibility'] ?? $post->visibility
            );
            $validated['notify_targets'] = $notifyTargets;
            $validated['visibility'] = in_array('public', $notifyTargets, true) ? 'public' : 'federation';
        }

        if (array_key_exists('body', $validated)) {
            $hasMedia = $request->hasFile('images')
                || $request->hasFile('videos')
                || ! empty($validated['uploaded_videos'] ?? [])
                || $post->images()->exists()
                || $post->videos()->exists()
                || (($validated['link_kind'] ?? $request->input('link_kind')) === 'google_drive_video');
            $rawBody = (string) $validated['body'];
            $validated['body'] = (trim($rawBody) === '' && $hasMedia)
                ? ''
                : $this->postBodyValidator->assertValid($rawBody);
        }

        if (array_key_exists('title', $validated)) {
            $title = is_string($validated['title']) ? trim($validated['title']) : '';
            $nextType = $validated['type'] ?? $post->type;
            if ($nextType === 'update') {
                $validated['title'] = $title;
                if ($title !== '') {
                    $this->prohibitedWords->assertClean($title, 'title');
                }
            } else {
                $validated['title'] = $title !== '' ? $title : null;
                if ($title !== '') {
                    $this->prohibitedWords->assertClean($title, 'title');
                }
            }
        }

        if (array_key_exists('link_url', $validated) || $request->has('link_url')) {
            $validated['link_url'] = $this->googleDriveVideoUrl->normalizeForStorage(
                $request->input('link_url'),
                ($validated['link_kind'] ?? $request->input('link_kind')) === 'google_drive_video'
            );
        }

        $post->update(collect($validated)->except([
            'images',
            'videos',
            'uploaded_videos',
            'removed_image_ids',
            'removed_video_ids',
            'clear_videos',
            'link_kind',
        ])->all());

        $removedIds = array_values(array_filter(array_map('intval', (array) $request->input('removed_image_ids', []))));
        if ($removedIds !== []) {
            $post->images()->whereIn('id', $removedIds)->delete();
        }

        $removedVideoIds = array_values(array_filter(array_map('intval', (array) $request->input('removed_video_ids', []))));
        if ($removedVideoIds !== []) {
            $post->videos()->whereIn('id', $removedVideoIds)->delete();
        }

        if ($request->boolean('clear_videos')) {
            $post->videos()->delete();
        }

        $currentCount = $post->images()->count();
        if ($request->hasFile('images') && $currentCount < self::MAX_IMAGES) {
            $remaining = self::MAX_IMAGES - $currentCount;
            $this->storePostImages($post, $request, $currentCount, $remaining);
        }

        $linkForVideos = array_key_exists('link_url', $validated) ? $validated['link_url'] : $post->link_url;
        $requireDrive = ($validated['link_kind'] ?? $request->input('link_kind')) === 'google_drive_video';
        $this->storePostVideos($post, $request, $linkForVideos, $requireDrive);
        $this->storeUploadedVideoMetadata($post, (array) ($validated['uploaded_videos'] ?? []));

        app(SkFedPageCache::class)->bump('community.feed');

        return response()->json($this->formatPost(
            $post->load(['barangay', 'comments.user', 'comments.reactions', 'user', 'images', 'videos', 'reactions.user.officialProfile'])
                ->loadCount('reactions'),
            Auth::id()
        ));
    }

    public function uploadVideo(Request $request): JsonResponse
    {
        $request->validate([
            'video' => 'required|file|mimetypes:'.implode(',', self::ALLOWED_VIDEO_MIMES).'|max:'.$this->maxVideoKilobytes(),
        ]);

        $file = $request->file('video');
        try {
            $result = $this->cloudinary->uploadCommunityVideo($file);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'The video failed to upload. '.$e->getMessage(),
            ], 422);
        }

        if (($result['public_id'] ?? '') === '' || ($result['url'] ?? '') === '') {
            return response()->json([
                'message' => 'Unable to upload video. Please try again.',
            ], 422);
        }

        return response()->json([
            'provider' => AnnouncementVideo::PROVIDER_CLOUDINARY,
            'public_id' => $result['public_id'],
            'video_url' => $result['url'],
            'bytes' => $result['bytes'] ?? $file->getSize(),
            'mime_type' => $result['mime_type'] ?? $file->getMimeType(),
        ], 201);
    }

    // DELETE /api/community-feed/{id}
    public function destroy(int $id): JsonResponse
    {
        $post = Announcement::where('id', $id)
            ->where('user_id', Auth::id())
            ->whereRaw('"is_federation_wide" = true')
            ->firstOrFail();

        app(DeletedPostsService::class)
            ->archive($post, Auth::user());

        app(SkFedPageCache::class)->bump('community.feed');
        app(SkFedPageCache::class)->bump('archive.deleted_posts');

        return response()->json(['success' => true]);
    }

    // GET /api/community-feed/{id}/likes
    public function likes(int $id): JsonResponse
    {
        $this->findTenantVisiblePost($id);

        $reactions = AnnouncementReaction::query()
            ->with('user.officialProfile')
            ->where('community_feed_id', $id)
            ->latest()
            ->get();

        $reactors = $reactions->map(fn (AnnouncementReaction $reaction) => [
            'name' => $this->resolveReactorName($reaction),
            'avatar_url' => $this->resolveReactorAvatar($reaction),
            'role_label' => $this->resolveReactorRoleLabel($reaction),
            'reaction_type' => $reaction->reaction_type ?: 'like',
        ])->values();

        return response()->json([
            'count' => $reactors->count(),
            'reaction_counts' => $this->countsFromReactions($reactions),
            'reactors' => $reactors,
        ]);
    }

    // POST /api/community-feed/{id}/react
    public function react(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $post = $this->findTenantVisiblePost($id);
        $validated = $request->validate([
            'reaction_type' => ['nullable', 'string', Rule::in(AnnouncementReaction::TYPES)],
            'client_seq' => ['sometimes', 'integer', 'min:1'],
        ]);
        $type = $validated['reaction_type'] ?? 'like';
        $seq = (int) ($validated['client_seq'] ?? 0);
        $seqKey = "community-feed-reaction-seq:{$user->id}:post:{$id}";
        $lock = Cache::lock("community-feed-reaction:{$user->id}:post:{$id}", 8);

        $result = $lock->block(5, function () use ($id, $user, $type, $seq, $seqKey) {
            return DB::transaction(function () use ($id, $user, $type, $seq, $seqKey) {
                $existing = AnnouncementReaction::query()
                    ->where('community_feed_id', $id)
                    ->where('user_id', $user->id)
                    ->where('user_type', 'sk_fed')
                    ->lockForUpdate()
                    ->first();

                if ($this->isStaleReactionSeq($seqKey, $seq)) {
                    return ['reaction' => $existing?->reaction_type, 'created' => false];
                }

                if ($existing && $existing->reaction_type === $type) {
                    $existing->delete();

                    return ['reaction' => null, 'created' => false];
                }

                if ($existing) {
                    $existing->update(['reaction_type' => $type]);

                    return ['reaction' => $type, 'created' => false];
                }

                AnnouncementReaction::create([
                    'community_feed_id' => $id,
                    'user_id' => $user->id,
                    'user_type' => 'sk_fed',
                    'reaction_type' => $type,
                ]);

                return ['reaction' => $type, 'created' => true];
            });
        });

        $userReaction = $result['reaction'];

        if ($result['created']) {
            $postLabel = $this->notificationService->postLabel($post->title, $post->body);
            if ($post->is_federation_wide) {
                $this->notificationService->notifyFederationPortalUsersCommunityFeedActivity(
                    [(int) $user->id],
                    (int) $post->id,
                    "{$user->name} liked a federation post",
                    $postLabel,
                    'reactions',
                );
            } elseif ((int) $post->user_id !== (int) $user->id) {
                $this->notificationService->notifyCommunityFeedLike(
                    (int) $post->user_id,
                    (string) $user->name,
                    $postLabel,
                    $post->id,
                );
            }
        }

        $counts = $this->reactionCountsForPost($id);
        $count = array_sum($counts);
        $reactions = AnnouncementReaction::with('user.officialProfile')
            ->where('community_feed_id', $id)
            ->latest()
            ->limit(12)
            ->get();

        $this->bumpFeedCache();

        return response()->json([
            'liked' => $userReaction !== null,
            'count' => $count,
            'reaction_type' => $userReaction,
            'reaction_counts' => $counts,
            'reactions_summary' => $this->formatReactionsSummary($reactions, $count),
        ]);
    }

    public function commentReact(Request $request, int $id, int $commentId): JsonResponse
    {
        $user = Auth::user();
        $this->findTenantVisiblePost($id);
        $comment = AnnouncementComment::query()
            ->where('community_feed_id', $id)
            ->where('id', $commentId)
            ->firstOrFail();

        $validated = $request->validate([
            'reaction_type' => ['required', 'string', Rule::in(AnnouncementReaction::TYPES)],
            'client_seq' => ['sometimes', 'integer', 'min:1'],
        ]);
        $type = $validated['reaction_type'];
        $seq = (int) ($validated['client_seq'] ?? 0);
        $seqKey = "community-feed-reaction-seq:{$user->id}:comment:{$comment->id}";
        $lock = Cache::lock("community-feed-reaction:{$user->id}:comment:{$comment->id}", 8);

        $userReaction = $lock->block(5, function () use ($comment, $user, $type, $seq, $seqKey) {
            return DB::transaction(function () use ($comment, $user, $type, $seq, $seqKey) {
                $existing = AnnouncementCommentReaction::query()
                    ->where('comment_id', $comment->id)
                    ->where('user_id', $user->id)
                    ->where('user_type', 'sk_fed')
                    ->lockForUpdate()
                    ->first();

                if ($this->isStaleReactionSeq($seqKey, $seq)) {
                    return $existing?->reaction_type;
                }

                if ($existing && $existing->reaction_type === $type) {
                    $existing->delete();

                    return null;
                }

                if ($existing) {
                    $existing->update(['reaction_type' => $type]);

                    return $type;
                }

                AnnouncementCommentReaction::create([
                    'comment_id' => $comment->id,
                    'user_id' => $user->id,
                    'user_type' => 'sk_fed',
                    'reaction_type' => $type,
                ]);

                return $type;
            });
        });

        $counts = $this->reactionCountsForComment($comment->id);

        $this->bumpFeedCache();

        return response()->json([
            'liked' => $userReaction !== null,
            'count' => array_sum($counts),
            'reaction_type' => $userReaction,
            'reaction_counts' => $counts,
        ]);
    }

    // POST /api/community-feed/{id}/comment
    public function comment(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $limiter = app(FeedCommentRateLimiter::class);
        $cooldown = $limiter->check('sk_fed', (int) $user->id);

        if (! $cooldown['allowed']) {
            return response()->json([
                'message' => $cooldown['message'],
                'retry_after' => $cooldown['retry_after'],
                'rate_limit' => [
                    'locked' => true,
                    'retry_after' => $cooldown['retry_after'],
                    'burst_used' => $cooldown['burst_used'],
                    'burst_remaining' => $cooldown['burst_remaining'],
                ],
            ], 429);
        }

        $request->validate([
            'body' => 'required|string|max:'.FeedCommentRateLimiter::MAX_BODY_LENGTH,
            'parent_id' => 'nullable|string|max:255',
        ]);

        $this->prohibitedWords->assertClean((string) $request->input('body'), 'body');

        $post = $this->findTenantVisiblePost($id);

        $parentId = $request->filled('parent_id')
            ? OpaqueUrlId::decode((string) $request->input('parent_id'))
            : null;
        if ($parentId) {
            $parent = AnnouncementComment::query()
                ->where('id', (int) $parentId)
                ->where('community_feed_id', $id)
                ->firstOrFail();

            if ($parent->parent_id !== null) {
                $parentId = $parent->parent_id;
            }
        }

        $comment = AnnouncementComment::create([
            'community_feed_id' => $id,
            'parent_id' => $parentId,
            'user_id' => $user->id,
            'user_type' => 'sk_fed',
            'author_name' => Str::limit($user->name, 50, '...'),
            'body' => $request->body,
        ]);

        if ((int) $post->user_id !== (int) $user->id) {
            $postLabel = $this->notificationService->postLabel($post->title, $post->body);
            $isReply = $parentId !== null && (int) $parentId > 0;
            if ($post->is_federation_wide) {
                $this->notificationService->notifyFederationPortalUsersCommunityFeedActivity(
                    [(int) $user->id],
                    (int) $post->id,
                    $isReply
                        ? "{$user->name} replied on a federation post"
                        : "{$user->name} commented on a federation post",
                    Str::limit(trim((string) $request->body), 160) ?: $postLabel,
                    'comments',
                );
            } else {
                $this->notificationService->notifyCommunityFeedComment(
                    (int) $post->user_id,
                    (string) $user->name,
                    $postLabel,
                    $post->id,
                    $request->body,
                    (bool) $isReply,
                );
            }
        }

        $rateLimit = $limiter->hit('sk_fed', (int) $user->id);
        $payload = $this->formatComment($comment->load(['user', 'reactions']), $user->id);
        $payload['rate_limit'] = [
            'locked' => $rateLimit['locked'],
            'retry_after' => $rateLimit['retry_after'],
            'burst_used' => $rateLimit['burst_used'],
            'burst_remaining' => $rateLimit['burst_remaining'],
            'message' => $rateLimit['message'],
        ];

        $this->bumpFeedCache();

        return response()->json($payload, 201);
    }

    public function updateComment(Request $request, int $id, int $commentId): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'body' => 'required|string|max:'.FeedCommentRateLimiter::MAX_BODY_LENGTH,
        ]);

        $this->prohibitedWords->assertClean($validated['body'], 'body');

        $comment = $this->ownedComment($id, $commentId, $user);
        $comment->update(['body' => $validated['body']]);

        $this->bumpFeedCache();

        return response()->json(
            $this->formatComment($comment->fresh(['user', 'reactions']), $user->id)
        );
    }

    public function destroyComment(int $id, int $commentId): JsonResponse
    {
        $user = Auth::user();
        $comment = AnnouncementComment::query()
            ->where('community_feed_id', $id)
            ->where('id', $commentId)
            ->firstOrFail();

        $isOwner = (int) $comment->user_id === (int) $user->id && $comment->user_type === 'sk_fed';
        $isAdmin = $user->role === User::ROLE_ADMIN;

        if (! $isOwner && ! $isAdmin) {
            return response()->json(['message' => 'You cannot delete this comment.'], 403);
        }

        $comment->delete();

        $this->bumpFeedCache();

        return response()->json(['success' => true]);
    }

    public function commentReactions(int $id, int $commentId): JsonResponse
    {
        $this->findTenantVisiblePost($id);
        $comment = AnnouncementComment::query()
            ->where('community_feed_id', $id)
            ->where('id', $commentId)
            ->firstOrFail();

        $reactions = AnnouncementCommentReaction::query()
            ->with('user.officialProfile')
            ->where('comment_id', $comment->id)
            ->latest()
            ->get();

        $reactors = $reactions->map(fn (AnnouncementCommentReaction $reaction) => [
            'name' => $reaction->user?->name ?: 'Member',
            'avatar_url' => $this->avatarService->resolveForUser(
                $reaction->user,
                $reaction->user_type,
                $reaction->user?->name
            ),
            'role_label' => $this->resolveRoleLabel($reaction->user_type, $reaction->user),
            'reaction_type' => $reaction->reaction_type ?: 'like',
        ])->values();

        return response()->json([
            'count' => $reactors->count(),
            'reaction_counts' => $this->countsFromReactions($reactions),
            'reactors' => $reactors,
        ]);
    }

    private function ownedComment(int $feedId, int $commentId, User $user): AnnouncementComment
    {
        return AnnouncementComment::query()
            ->where('community_feed_id', $feedId)
            ->where('id', $commentId)
            ->where('user_id', $user->id)
            ->where('user_type', 'sk_fed')
            ->firstOrFail();
    }

    // POST /api/community-feed/upload-image
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:5120']);

        try {
            $publicId = 'fed_post_'.Auth::id().'_'.Str::random(8);
            $result = $this->cloudinary->upload($request->file('image'), $publicId);

            return response()->json(['url' => $result['url']]);
        } catch (Throwable $e) {
            \Log::error('Cloudinary upload failed: '.$e->getMessage());

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function formatPost(Announcement $post, int $userId): array
    {
        $userReaction = $this->userReactionType(
            $post->relationLoaded('reactions') ? $post->reactions : null,
            $userId,
            'sk_fed'
        );
        $reactionCounts = $this->countsFromReactions(
            $post->relationLoaded('reactions') ? $post->reactions : collect()
        );

        $authorName = Str::limit(
            $post->is_federation_wide
                ? 'SK Federation'
                : ('SK Brgy. '.($post->barangay?->name ?? 'Barangay')),
            50,
            '...'
        );

        $imageRecords = $post->relationLoaded('images') ? $post->images : collect();
        $imageItems = $imageRecords
            ->map(fn ($img) => [
                'id' => $img->id,
                'url' => $this->cloudinary->normalizeUrl($img->image_url),
                'sort_order' => (int) ($img->sort_order ?? 0),
                'created_at' => optional($img->created_at)?->toIso8601String(),
            ])
            ->filter(fn ($item) => ! empty($item['url']))
            ->values()
            ->all();
        $images = array_values(array_unique(array_column($imageItems, 'url')));
        $videoItems = $this->formatVideos($post);
        $primaryDrive = collect($videoItems)->first(
            fn ($v) => ($v['provider'] ?? null) === AnnouncementVideo::PROVIDER_GOOGLE_DRIVE
        );
        $legacyDrive = $primaryDrive ?: $this->googleDriveVideoUrl->present($post->link_url);
        $canShare = $post->isShareable();
        $shareUrl = $canShare && filled($post->share_token)
            ? PortalAppUrl::join(
                PortalAppUrl::selfApp(),
                '/community/post/'.$post->share_token
            )
            : null;
        $comments = $post->relationLoaded('comments') ? $post->comments : collect();

        $barangaySlug = null;
        $profileUrl = null;
        if ($post->is_federation_wide) {
            $profileUrl = route('sk-fed-profile');
        } elseif ($post->barangay) {
            $barangaySlug = app(CommunityFeedService::class)
                ->slugForBarangayName((string) $post->barangay->name);
            if ($barangaySlug) {
                $profileUrl = route('skfed.barangay-profile', ['slug' => $barangaySlug]);
            }
        }

        return [
            'id' => OpaqueUrlId::encode($post->id),
            'type' => $post->type,
            'title' => $post->title,
            'body' => $post->body,
            'image_url' => $images[0] ?? null,
            'images' => $images,
            'image_items' => $imageItems,
            'videos' => $videoItems,
            'link_url' => $post->link_url,
            'google_drive_video' => $legacyDrive,
            'visibility' => $post->visibility ?: 'public',
            'notify_targets' => $post->notifyTargets(),
            'can_share' => $canShare,
            'share_url' => $shareUrl,
            'is_federation_wide' => (bool) $post->is_federation_wide,
            'barangay_name' => $post->barangay?->name,
            'barangay_slug' => $barangaySlug,
            'barangay_logo_url' => $this->avatarService->barangayLogo((int) $post->barangay_id),
            'author_name' => $authorName,
            'author_avatar_url' => $this->avatarService->resolveForPost($post),
            'profile_url' => $profileUrl,
            'owned' => $post->user_id === $userId && $post->is_federation_wide,
            'likes' => array_sum($reactionCounts),
            'liked' => $userReaction !== null,
            'reaction_type' => $userReaction,
            'reaction_counts' => $reactionCounts,
            'time' => $post->created_at?->diffForHumans() ?? 'Just now',
            'reactions_summary' => $this->formatReactionsSummary(
                $post->relationLoaded('reactions') ? $post->reactions : collect(),
                array_sum($reactionCounts),
            ),
            'comment_count' => $comments->count(),
            'comments' => $this->formatCommentTree($comments, $userId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatComment(AnnouncementComment $comment, int $userId): array
    {
        $reactionCounts = $this->countsFromReactions(
            $comment->relationLoaded('reactions') ? $comment->reactions : collect()
        );
        $userReaction = $this->userReactionType(
            $comment->relationLoaded('reactions') ? $comment->reactions : null,
            $userId,
            'sk_fed'
        );

        return [
            'id' => OpaqueUrlId::encode($comment->id),
            'is_reply' => $comment->parent_id !== null,
            'author_name' => Str::limit($comment->author_name, 50, '...'),
            'body' => $comment->body,
            'time' => $comment->created_at?->diffForHumans() ?? 'Just now',
            'created_at' => $comment->created_at?->toIso8601String(),
            'user_type' => $comment->user_type,
            'owned' => (int) $comment->user_id === $userId && $comment->user_type === 'sk_fed',
            'avatar_url' => $this->avatarService->resolveForComment($comment),
            'author_avatar_url' => $this->avatarService->resolveForComment($comment),
            'likes' => array_sum($reactionCounts),
            'liked' => $userReaction !== null,
            'reaction_type' => $userReaction,
            'reaction_counts' => $reactionCounts,
            'reply_count' => $comment->relationLoaded('replies') ? $comment->replies->count() : 0,
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies->map(fn ($reply) => $this->formatComment($reply, $userId))->values()->all()
                : [],
        ];
    }

    /**
     * @param  Collection<int, AnnouncementComment>  $comments
     * @return list<array<string, mixed>>
     */
    private function formatCommentTree(Collection $comments, int $userId): array
    {
        $byParent = $comments->groupBy(fn (AnnouncementComment $comment) => $comment->parent_id ?? 0);

        return $byParent->get(0, collect())
            ->map(function (AnnouncementComment $comment) use ($byParent, $userId) {
                $comment->setRelation('replies', $byParent->get($comment->id, collect())->values());

                return $this->formatComment($comment, $userId);
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AnnouncementReaction>  $reactions
     * @return array{count: int, reactors: list<array{name: string, avatar_url: string, role_label: string}>}
     */
    private function formatReactionsSummary(Collection $reactions, int $totalCount): array
    {
        $reactors = $reactions->map(fn (AnnouncementReaction $reaction) => [
            'name' => $this->resolveReactorName($reaction),
            'avatar_url' => $this->resolveReactorAvatar($reaction),
            'role_label' => $this->resolveReactorRoleLabel($reaction),
            'reaction_type' => $reaction->reaction_type ?: 'like',
        ])->values();

        return [
            'count' => max(0, $totalCount),
            'reactors' => $reactors->take(8)->all(),
        ];
    }

    private function resolveReactorName(AnnouncementReaction $reaction): string
    {
        return Str::limit($reaction->user?->name ?: 'Member', 50, '...');
    }

    private function resolveReactorAvatar(AnnouncementReaction $reaction): string
    {
        return $this->avatarService->resolveForReaction($reaction);
    }

    private function resolveReactorRoleLabel(AnnouncementReaction $reaction): string
    {
        return $this->resolveRoleLabel($reaction->user_type, $reaction->user);
    }

    private function resolveRoleLabel(?string $userType, ?User $user): string
    {
        return match ($userType) {
            'kabataan' => 'Kabataan Member',
            'sk_fed' => $this->resolveSkFedRoleLabel($user),
            'sk_official' => $this->resolveSkOfficialRoleLabel($user),
            default => 'Member',
        };
    }

    private function resolveSkOfficialRoleLabel(?User $user): string
    {
        $position = trim((string) ($user?->officialProfile?->position ?? ''));
        if ($position === '') {
            return 'SK Official';
        }

        $options = OfficialProfile::officialPositionOptions();
        if (isset($options[$position])) {
            return $options[$position];
        }

        if (strcasecmp($position, 'Chairman') === 0) {
            return 'SK Chairperson';
        }

        return 'SK Official ('.$position.')';
    }

    private function resolveSkFedRoleLabel(?User $user): string
    {
        $federationPosition = trim((string) ($user?->officialProfile?->federation_position ?? ''));

        return $federationPosition !== '' ? $federationPosition : 'SK Federation';
    }

    /**
     * @return list<array{url: string, public_id: string}>
     */
    private function storePostImages(Announcement $post, Request $request, int $sortStart = 0, ?int $maxNew = null): array
    {
        $files = $request->file('images', []);
        if (! is_array($files)) {
            $files = [$files];
        }

        $files = array_values(array_filter($files));
        if ($maxNew !== null) {
            $files = array_slice($files, 0, max(0, $maxNew));
        }

        $uploaded = [];
        $sort = $sortStart;
        $now = now();

        foreach ($files as $file) {
            if ($file === null || $sort >= self::MAX_IMAGES) {
                continue;
            }

            try {
                $publicId = 'fed_post_'.$post->id.'_'.Str::random(10);
                $result = $this->cloudinary->upload($file, $publicId);

                AnnouncementImage::create([
                    'community_feed_id' => $post->id,
                    'image_url' => $result['url'],
                    'public_id' => $result['public_id'],
                    'sort_order' => $sort,
                    'created_at' => $now,
                ]);

                $uploaded[] = ['url' => $result['url'], 'public_id' => $result['public_id']];
                $sort++;
            } catch (Throwable) {
                continue;
            }
        }

        return $uploaded;
    }

    /**
     * @return array<string, int>
     */
    private function emptyReactionCounts(): array
    {
        return array_fill_keys(AnnouncementReaction::TYPES, 0);
    }

    /**
     * @param  Collection<int, object>  $reactions
     * @return array<string, int>
     */
    private function countsFromReactions(Collection $reactions): array
    {
        $counts = $this->emptyReactionCounts();
        foreach ($reactions as $reaction) {
            $type = (string) ($reaction->reaction_type ?: 'like');
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function reactionCountsForPost(int $feedId): array
    {
        return $this->countsFromQuery(
            AnnouncementReaction::query()
                ->where('community_feed_id', $feedId)
                ->selectRaw('reaction_type, COUNT(*) as aggregate')
                ->groupBy('reaction_type')
                ->pluck('aggregate', 'reaction_type')
        );
    }

    /**
     * @return array<string, int>
     */
    private function reactionCountsForComment(int $commentId): array
    {
        return $this->countsFromQuery(
            AnnouncementCommentReaction::query()
                ->where('comment_id', $commentId)
                ->selectRaw('reaction_type, COUNT(*) as aggregate')
                ->groupBy('reaction_type')
                ->pluck('aggregate', 'reaction_type')
        );
    }

    /**
     * @param  Collection<string, mixed>  $grouped
     * @return array<string, int>
     */
    private function countsFromQuery(Collection $grouped): array
    {
        $counts = $this->emptyReactionCounts();
        foreach ($grouped as $type => $count) {
            $key = $type !== '' && $type !== null ? (string) $type : 'like';
            if (isset($counts[$key])) {
                $counts[$key] = (int) $count;
            }
        }

        return $counts;
    }

    /**
     * @param  Collection<int, object>|null  $reactions
     */
    private function userReactionType(?Collection $reactions, int $userId, string $userType): ?string
    {
        if ($reactions === null) {
            return null;
        }

        $match = $reactions->first(
            fn ($reaction) => (int) $reaction->user_id === $userId && $reaction->user_type === $userType
        );

        return $match?->reaction_type ? (string) $match->reaction_type : ($match ? 'like' : null);
    }

    private function isStaleReactionSeq(string $cacheKey, int $seq): bool
    {
        if ($seq <= 0) {
            return false;
        }

        $last = (int) Cache::get($cacheKey, 0);
        if ($seq < $last) {
            return true;
        }

        Cache::put($cacheKey, $seq, now()->addMinutes(10));

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formatVideos(Announcement $post): array
    {
        $records = $post->relationLoaded('videos') ? $post->videos : collect();

        return $records
            ->filter(static fn (AnnouncementVideo $video): bool => ($video->status ?: 'active') === 'active')
            ->map(function (AnnouncementVideo $video) {
                if ($video->provider === AnnouncementVideo::PROVIDER_GOOGLE_DRIVE) {
                    $drive = $this->googleDriveVideoUrl->present(
                        $video->video_url ?: ('https://drive.google.com/file/d/'.$video->google_drive_file_id.'/view')
                    );

                    return [
                        'id' => $video->id,
                        'provider' => AnnouncementVideo::PROVIDER_GOOGLE_DRIVE,
                        'video_url' => $drive['media_url'] ?? $video->video_url,
                        'public_id' => null,
                        'google_drive_file_id' => $drive['external_id'] ?? $video->google_drive_file_id,
                        'google_drive_folder_id' => $video->google_drive_folder_id,
                        'name' => $video->name,
                        'preview_url' => $drive['preview_url'] ?? null,
                        'open_url' => $drive['open_url'] ?? ($video->web_view_link ?: null),
                        'mime_type' => $video->mime_type,
                        'bytes' => $video->bytes,
                        'drive_created_at' => optional($video->drive_created_at)?->toIso8601String(),
                        'sort_order' => (int) ($video->sort_order ?? 0),
                        'created_at' => optional($video->created_at)?->toIso8601String(),
                        'status' => $video->status ?: 'active',
                        'media_type' => 'google_drive_video',
                        'external_provider' => 'google_drive',
                        'external_id' => $drive['external_id'] ?? $video->google_drive_file_id,
                    ];
                }

                return [
                    'id' => $video->id,
                    'provider' => AnnouncementVideo::PROVIDER_CLOUDINARY,
                    'video_url' => $video->video_url,
                    'public_id' => $video->public_id,
                    'google_drive_file_id' => null,
                    'google_drive_folder_id' => null,
                    'name' => $video->name,
                    'preview_url' => null,
                    'open_url' => null,
                    'mime_type' => $video->mime_type,
                    'bytes' => $video->bytes,
                    'drive_created_at' => null,
                    'sort_order' => (int) ($video->sort_order ?? 0),
                    'created_at' => optional($video->created_at)?->toIso8601String(),
                    'status' => $video->status ?: 'active',
                    'media_type' => 'cloudinary_video',
                    'external_provider' => 'cloudinary',
                    'external_id' => $video->public_id,
                ];
            })->values()->all();
    }

    private function storePostVideos(
        Announcement $post,
        Request $request,
        ?string $linkUrl,
        bool $isDriveLink
    ): void {
        $now = now();
        $sort = (int) ($post->videos()->max('sort_order') ?? -1);
        $maxCount = $this->maxVideoCount();

        if ($isDriveLink && $linkUrl) {
            $drive = $this->googleDriveVideoUrl->parse($linkUrl);
            if ($drive) {
                $post->videos()
                    ->where('provider', AnnouncementVideo::PROVIDER_GOOGLE_DRIVE)
                    ->delete();

                AnnouncementVideo::create([
                    'community_feed_id' => $post->id,
                    'provider' => AnnouncementVideo::PROVIDER_GOOGLE_DRIVE,
                    'video_url' => $drive['media_url'],
                    'public_id' => null,
                    'google_drive_file_id' => $drive['external_id'],
                    'mime_type' => 'video/*',
                    'bytes' => null,
                    'sort_order' => ++$sort,
                    'created_at' => $now,
                ]);
            }
        }

        if (! $request->hasFile('videos')) {
            return;
        }

        $files = $request->file('videos');
        if (! is_array($files)) {
            $files = [$files];
        }

        $current = $post->videos()->where('provider', AnnouncementVideo::PROVIDER_CLOUDINARY)->count();
        $slots = max(0, $maxCount - $current);

        foreach (array_slice($files, 0, $slots) as $file) {
            if ($file === null) {
                continue;
            }

            try {
                $result = $this->cloudinary->uploadCommunityVideo($file);
                if (($result['public_id'] ?? '') === '' || ($result['url'] ?? '') === '') {
                    continue;
                }

                AnnouncementVideo::create([
                    'community_feed_id' => $post->id,
                    'provider' => AnnouncementVideo::PROVIDER_CLOUDINARY,
                    'video_url' => $result['url'],
                    'public_id' => $result['public_id'],
                    'google_drive_file_id' => null,
                    'mime_type' => $result['mime_type'] ?? $file->getMimeType(),
                    'bytes' => $result['bytes'] ?? $file->getSize(),
                    'sort_order' => ++$sort,
                    'created_at' => $now,
                ]);
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $uploaded
     */
    private function storeUploadedVideoMetadata(Announcement $post, array $uploaded): void
    {
        if ($uploaded === []) {
            return;
        }

        $now = now();
        $sort = (int) ($post->videos()->max('sort_order') ?? -1);
        $maxCount = $this->maxVideoCount();
        $current = $post->videos()->where('provider', AnnouncementVideo::PROVIDER_CLOUDINARY)->count();
        $slots = max(0, $maxCount - $current);
        $cloudName = trim((string) config('services.cloudinary.cloud_name', ''));
        $folder = trim((string) config('services.cloudinary.community_video_folder', 'SK_Federations_community_video'), '/');

        foreach (array_slice($uploaded, 0, $slots) as $row) {
            $publicId = trim((string) ($row['public_id'] ?? ''));
            $videoUrl = trim((string) ($row['video_url'] ?? ''));
            if ($publicId === '' || $videoUrl === '') {
                continue;
            }
            if (! str_contains($videoUrl, 'res.cloudinary.com')) {
                continue;
            }
            if ($cloudName !== '' && ! str_contains($videoUrl, '/'.$cloudName.'/')) {
                continue;
            }
            $inFolder = $folder === ''
                || str_starts_with($publicId, $folder.'/')
                || $publicId === $folder
                || str_contains($videoUrl, '/'.$folder.'/')
                || str_contains($videoUrl, '/video/upload/');
            if (! $inFolder) {
                continue;
            }
            if ($post->videos()->where('public_id', $publicId)->exists()) {
                continue;
            }

            AnnouncementVideo::create([
                'community_feed_id' => $post->id,
                'provider' => AnnouncementVideo::PROVIDER_CLOUDINARY,
                'video_url' => $videoUrl,
                'public_id' => $publicId,
                'google_drive_file_id' => null,
                'mime_type' => isset($row['mime_type']) ? (string) $row['mime_type'] : null,
                'bytes' => isset($row['bytes']) ? (int) $row['bytes'] : null,
                'sort_order' => ++$sort,
                'created_at' => $now,
                'status' => 'active',
            ]);
        }
    }

    private function maxVideoCount(): int
    {
        return max(1, (int) config('services.cloudinary.community_video_max_count', 30));
    }

    private function maxVideoKilobytes(): int
    {
        $bytes = (int) config('services.cloudinary.community_video_max_bytes', 524288000);

        return max(1, (int) floor($bytes / 1024));
    }

    private function bumpFeedCache(): void
    {
        app(SkFedPageCache::class)->bump('community.feed');
    }

    /**
     * @return list<string>
     */
    public function normalizeNotifyTargets(mixed $raw, mixed $legacyVisibility = null): array
    {
        $allowed = Announcement::NOTIFY_TARGET_OPTIONS;
        $targets = [];

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_filter(array_map('trim', explode(',', $raw)));
            }
        }

        if (is_array($raw)) {
            foreach ($raw as $item) {
                $key = strtolower(trim((string) $item));
                if (in_array($key, $allowed, true) && ! in_array($key, $targets, true)) {
                    $targets[] = $key;
                }
            }
        }

        if ($targets === []) {
            return Announcement::defaultNotifyTargetsForVisibility(
                is_string($legacyVisibility) ? $legacyVisibility : 'public'
            );
        }

        return Announcement::expandNotifyTargets($targets);
    }

    public function dispatchFederationPostNotifications(int $postId, int $authorUserId, bool $sendEmail): void
    {
        dispatch(function () use ($postId, $authorUserId, $sendEmail): void {
            $post = Announcement::query()->find($postId);
            if ($post === null) {
                return;
            }

            $broadcast = app(FederationCommunityFeedBroadcastService::class);

            try {
                $broadcast->broadcastInApp($post, $authorUserId);
            } catch (Throwable $exception) {
                Log::error('[CommunityFeed] In-app broadcast failed.', [
                    'community_feed_id' => $postId,
                    'error' => $exception->getMessage(),
                ]);
                report($exception);
            }

            if (! $sendEmail) {
                return;
            }

            try {
                $broadcast->broadcastEmail($post);
            } catch (Throwable $exception) {
                Log::error('[CommunityFeed] Email broadcast failed.', [
                    'community_feed_id' => $postId,
                    'error' => $exception->getMessage(),
                ]);
                report($exception);
            }
        })->afterResponse();
    }

    private function findTenantVisiblePost(int $id): Announcement
    {
        $user = Auth::user();
        abort_unless($user !== null && (int) $user->tenant_id > 0, 403);

        return Announcement::query()
            ->visibleToTenant((int) $user->tenant_id)
            ->findOrFail($id);
    }

    private function normalizeEmptyLinkUrl(Request $request): void
    {
        if ($request->has('link_url') && trim((string) $request->input('link_url')) === '') {
            $request->merge(['link_url' => null]);
        }
    }
}
