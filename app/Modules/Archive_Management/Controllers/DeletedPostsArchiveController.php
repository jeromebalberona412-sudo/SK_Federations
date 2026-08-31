<?php

namespace App\Modules\Archive_Management\Controllers;

use App\Modules\Archive_Management\Services\FederationPostArchiveService;
use App\Modules\CommunityFeed\Services\CloudinaryService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeletedPostsArchiveController extends Controller
{
    public function __construct(
        private readonly FederationPostArchiveService $archiveService,
        private readonly CloudinaryService $cloudinary,
    ) {
    }

    public function index(): View
    {
        return view('archive-management::deleted-posts');
    }

    public function data(Request $request): JsonResponse
    {
        $pageCache = app(\App\Services\SkFedPageCache::class);
        $parts = [
            'page' => (int) $request->query('page', 1),
            'search' => trim((string) $request->query('search', '')),
        ];

        $payload = $pageCache->remember('archive.deleted_posts', function () use ($request) {
            $query = Announcement::query()
                ->archived()
                ->federationWide()
                ->with(['user', 'barangay', 'images'])
                ->orderByDesc('archived_at');

            if ($search = trim((string) $request->query('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ilike', '%'.$search.'%')
                        ->orWhere('body', 'ilike', '%'.$search.'%');
                });
            }

            $posts = $query->paginate(12);

            return [
                'data' => collect($posts->items())->map(fn (Announcement $post) => $this->formatArchivedPost($post))->values()->all(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ];
        }, $parts);

        return response()->json($payload)->header('Cache-Control', 'private, max-age=60');
    }

    public function restore(int $id): JsonResponse
    {
        $post = $this->findArchivedPost($id);
        if ($post === null) {
            return response()->json([
                'success' => false,
                'message' => 'This archived post is no longer available. It may have been restored or permanently deleted.',
            ], 404);
        }

        $this->archiveService->restore($post);

        app(\App\Services\SkFedPageCache::class)->bump('archive.deleted_posts');
        app(\App\Services\SkFedPageCache::class)->bump('community.feed');

        return response()->json(['success' => true, 'message' => 'Post restored successfully.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $post = $this->findArchivedPost($id);
        if ($post === null) {
            return response()->json([
                'success' => false,
                'message' => 'This archived post is no longer available. It may have been restored or permanently deleted.',
            ], 404);
        }

        $this->archiveService->permanentlyDelete($post);

        app(\App\Services\SkFedPageCache::class)->bump('archive.deleted_posts');

        return response()->json(['success' => true, 'message' => 'Post permanently deleted.']);
    }

    private function findArchivedPost(int $id): ?Announcement
    {
        return Announcement::query()
            ->where('id', $id)
            ->archived()
            ->federationWide()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatArchivedPost(Announcement $post): array
    {
        $daysRemaining = $this->archiveService->daysRemaining($post->archived_at);

        return [
            'id' => $post->id,
            'type' => $post->type,
            'type_label' => ucfirst((string) $post->type),
            'title' => $post->title ?? '',
            'body' => $post->body ?? '',
            'author_name' => $post->user?->name ?? 'SK Federation',
            'created_at' => $post->created_at?->toIso8601String(),
            'archived_at' => $post->archived_at?->toIso8601String(),
            'days_remaining' => $daysRemaining,
            'days_tier' => $this->archiveService->daysRemainingTier($daysRemaining),
            'auto_delete_label' => $daysRemaining === 0
                ? 'Auto delete today'
                : 'Auto delete in '.$daysRemaining.' day'.($daysRemaining === 1 ? '' : 's'),
        ];
    }
}
