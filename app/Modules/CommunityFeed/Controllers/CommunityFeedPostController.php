<?php

namespace App\Modules\CommunityFeed\Controllers;

use App\Modules\Archive_Management\Services\FederationPostArchiveService;
use App\Modules\CommunityFeed\Services\CloudinaryService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Announcement;
use App\Modules\Shared\Models\AnnouncementComment;
use App\Modules\Shared\Models\AnnouncementCommentReaction;
use App\Modules\Shared\Models\AnnouncementImage;
use App\Modules\Shared\Models\AnnouncementReaction;
use App\Modules\Shared\Models\User;
use App\Services\CommunityFeedAvatarService;
use App\Services\FeedCommentRateLimiter;
use App\Services\SkFederationsNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class CommunityFeedPostController extends Controller
{
    private const MAX_IMAGES = 20;

    private const MAX_BODY_LENGTH = 2000;

    public function __construct(
        private readonly CloudinaryService $cloudinary,
        private readonly SkFederationsNotificationService $notificationService,
        private readonly CommunityFeedAvatarService $avatarService,
    ) {}

    // GET /api/community-feed?filter=all&page=1
    public function feed(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Announcement::with([
            'barangay',
            'comments.user',
            'comments.reactions',
            'user',
            'images',
            'reactions.user',
        ])
            ->withCount('reactions')
            ->active();

        $query->orderByDesc('created_at');

        $perPage = min(100, max(1, (int) $request->get('per_page', 100)));

        if ($request->filter && $request->filter !== 'all') {
            $query->where('type', $request->filter);
        }

        if ($request->filled('barangay_id')) {
            $query->where('barangay_id', (int) $request->barangay_id)
                ->whereRaw('"is_federation_wide" = false');
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', '%'.$search.'%'));
            });
        }

        $posts = $query->paginate($perPage);

        return response()->json([
            'data' => collect($posts->items())->map(fn ($p) => $this->formatPost($p, $user->id)),
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'total' => $posts->total(),
            'user_id' => $user->id,
        ]);
    }

    public function formatPostForPage(Announcement $post, int $userId): array
    {
        return $this->formatPost($post, $userId);
    }

    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $post = Announcement::query()
            ->active()
            ->with([
                'barangay',
                'comments.user',
                'comments.reactions',
                'user',
                'images',
                'reactions.user',
            ])
            ->withCount('reactions')
            ->findOrFail($id);

        return response()->json($this->formatPost($post, $user->id));
    }

    // POST /api/community-feed
    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'link_url' => filled($request->input('link_url')) ? $request->input('link_url') : null,
            'title' => filled($request->input('title')) ? $request->input('title') : null,
        ]);

        $validated = $request->validate([
            'type' => 'required|in:announcement,event,activity,program,update',
            'title' => 'nullable|string|max:255',
            'body' => 'required|string|max:'.self::MAX_BODY_LENGTH,
            'link_url' => 'nullable|url|max:4096',
            'images' => 'nullable|array|max:'.self::MAX_IMAGES,
            'images.*' => 'image|max:5120',
        ]);

        $user = Auth::user();
        $post = Announcement::create([
            'type' => $validated['type'],
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'],
            'link_url' => $validated['link_url'] ?? null,
            'user_id' => $user->id,
            'barangay_id' => null,
            'is_federation_wide' => true,
        ]);

        $this->storePostImages($post, $request);

        $fresh = Announcement::with([
            'barangay',
            'comments.user',
            'comments.reactions',
            'user',
            'images',
            'reactions.user',
        ])
            ->withCount('reactions')
            ->findOrFail($post->id);

        return response()->json($this->formatPost($fresh, $user->id), 201);
    }

    // PUT /api/community-feed/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $post = Announcement::where('id', $id)
            ->where('user_id', Auth::id())
            ->whereRaw('"is_federation_wide" = true')
            ->firstOrFail();

        $request->merge([
            'link_url' => filled($request->input('link_url')) ? $request->input('link_url') : null,
            'title' => filled($request->input('title')) ? $request->input('title') : null,
        ]);

        $validated = $request->validate([
            'type' => 'sometimes|in:announcement,event,activity,program,update',
            'title' => 'nullable|string|max:255',
            'body' => 'sometimes|string|max:'.self::MAX_BODY_LENGTH,
            'link_url' => 'nullable|url|max:4096',
            'images' => 'nullable|array|max:'.self::MAX_IMAGES,
            'images.*' => 'image|max:5120',
            'removed_image_ids' => 'nullable|array',
            'removed_image_ids.*' => 'integer',
        ]);

        $post->update(collect($validated)->except(['images', 'removed_image_ids'])->all());

        $removedIds = array_values(array_filter(array_map('intval', (array) $request->input('removed_image_ids', []))));
        if ($removedIds !== []) {
            $post->images()->whereIn('id', $removedIds)->delete();
        }

        $currentCount = $post->images()->count();
        if ($request->hasFile('images') && $currentCount < self::MAX_IMAGES) {
            $remaining = self::MAX_IMAGES - $currentCount;
            $this->storePostImages($post, $request, $currentCount, $remaining);
        }

        return response()->json($this->formatPost(
            $post->load(['barangay', 'comments.user', 'comments.reactions', 'user', 'images', 'reactions.user'])
                ->loadCount('reactions'),
            Auth::id()
        ));
    }

    // DELETE /api/community-feed/{id}
    public function destroy(int $id): JsonResponse
    {
        $post = Announcement::where('id', $id)
            ->where('user_id', Auth::id())
            ->whereRaw('"is_federation_wide" = true')
            ->firstOrFail();

        app(FederationPostArchiveService::class)
            ->archive($post, Auth::user());

        return response()->json(['success' => true]);
    }

    // GET /api/community-feed/{id}/likes
    public function likes(int $id): JsonResponse
    {
        Announcement::query()->findOrFail($id);

        $reactions = AnnouncementReaction::query()
            ->with('user')
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
        $post = Announcement::query()->findOrFail($id);
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
                $this->notificationService->notifyFederationPortalUsersExcept(
                    [(int) $user->id],
                    SkFederationsNotificationService::CATEGORY_COMMUNITY_FEED,
                    "{$user->name} liked a federation post",
                    $postLabel,
                    '/community-feed',
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
        $reactions = AnnouncementReaction::with('user')
            ->where('community_feed_id', $id)
            ->latest()
            ->limit(12)
            ->get();

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
        Announcement::query()->findOrFail($id);
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
            ], 429);
        }

        $request->validate([
            'body' => 'required|string|max:'.FeedCommentRateLimiter::MAX_BODY_LENGTH,
            'parent_id' => 'nullable|integer',
        ]);

        $post = Announcement::query()->findOrFail($id);

        $parentId = $request->input('parent_id');
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
            'author_name' => $user->name,
            'body' => $request->body,
        ]);

        if ((int) $post->user_id !== (int) $user->id) {
            $postLabel = $this->notificationService->postLabel($post->title, $post->body);
            if ($post->is_federation_wide) {
                $this->notificationService->notifyFederationPortalUsersExcept(
                    [(int) $user->id],
                    SkFederationsNotificationService::CATEGORY_COMMUNITY_FEED,
                    "{$user->name} commented on a federation post",
                    Str::limit(trim((string) $request->body), 160) ?: $postLabel,
                    '/community-feed',
                );
            } else {
                $this->notificationService->notifyCommunityFeedComment(
                    (int) $post->user_id,
                    (string) $user->name,
                    $postLabel,
                    $post->id,
                    $request->body,
                );
            }
        }

        $limiter->hit('sk_fed', (int) $user->id);

        return response()->json(
            $this->formatComment($comment->load(['user', 'reactions']), $user->id),
            201
        );
    }

    public function updateComment(Request $request, int $id, int $commentId): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'body' => 'required|string|max:'.FeedCommentRateLimiter::MAX_BODY_LENGTH,
        ]);

        $comment = $this->ownedComment($id, $commentId, $user);
        $comment->update(['body' => $validated['body']]);

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

        return response()->json(['success' => true]);
    }

    public function commentReactions(int $id, int $commentId): JsonResponse
    {
        Announcement::query()->findOrFail($id);
        $comment = AnnouncementComment::query()
            ->where('community_feed_id', $id)
            ->where('id', $commentId)
            ->firstOrFail();

        $reactions = AnnouncementCommentReaction::query()
            ->with('user')
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
            'role_label' => match ($reaction->user_type) {
                'sk_fed' => 'SK Federation',
                'sk_official' => 'SK Official',
                'kabataan' => 'Kabataan Member',
                default => 'Member',
            },
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

        $authorName = $post->user?->name
            ?? ($post->is_federation_wide ? 'SK Federation' : ('SK Brgy. '.($post->barangay?->name ?? '')));

        $imageRecords = $post->relationLoaded('images') ? $post->images : collect();
        $imageItems = $imageRecords
            ->map(fn ($img) => [
                'id' => $img->id,
                'url' => $this->cloudinary->normalizeUrl($img->image_url),
            ])
            ->filter(fn ($item) => ! empty($item['url']))
            ->values()
            ->all();
        $images = array_values(array_unique(array_column($imageItems, 'url')));
        $comments = $post->relationLoaded('comments') ? $post->comments : collect();

        return [
            'id' => $post->id,
            'type' => $post->type,
            'title' => $post->title,
            'body' => $post->body,
            'image_url' => $images[0] ?? null,
            'images' => $images,
            'image_items' => $imageItems,
            'link_url' => $post->link_url,
            'is_federation_wide' => (bool) $post->is_federation_wide,
            'barangay_name' => $post->barangay?->name,
            'barangay_logo_url' => $this->avatarService->barangayLogo((int) $post->barangay_id),
            'author_name' => $authorName,
            'author_avatar_url' => $this->avatarService->resolveForPost($post),
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
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'author_name' => $comment->author_name,
            'body' => $comment->body,
            'time' => $comment->created_at?->diffForHumans() ?? 'Just now',
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
        return $reaction->user?->name ?: 'Member';
    }

    private function resolveReactorAvatar(AnnouncementReaction $reaction): string
    {
        return $this->avatarService->resolveForReaction($reaction);
    }

    private function resolveReactorRoleLabel(AnnouncementReaction $reaction): string
    {
        return match ($reaction->user_type) {
            'sk_fed' => 'SK Federation',
            'sk_official' => 'SK Official',
            'kabataan' => 'Kabataan Member',
            default => 'Member',
        };
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
}
