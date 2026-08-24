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

        return response()->json([
            'data' => collect($posts->items())->map(fn (Announcement $post) => $this->formatArchivedPost($post)),
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'total' => $posts->total(),
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $post = $this->findArchivedPost($id);
        $this->archiveService->restore($post);

        return response()->json(['success' => true, 'message' => 'Post restored successfully.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $post = $this->findArchivedPost($id);
        $this->archiveService->permanentlyDelete($post);

        return response()->json(['success' => true, 'message' => 'Post permanently deleted.']);
    }

    private function findArchivedPost(int $id): Announcement
    {
        return Announcement::query()
            ->where('id', $id)
            ->archived()
            ->federationWide()
            ->firstOrFail();
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
