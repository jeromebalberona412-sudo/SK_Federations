<?php

namespace App\Modules\CommunityFeed\Controllers;

use App\Modules\CommunityFeed\Services\CommunityFeedService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityFeedController extends Controller
{
    public function __construct(private readonly CommunityFeedService $feedService) {}

    public function index(Request $request): View
    {
        $tenantId = $request->user()?->tenant_id;

        return view('community_feed::index', [
            'user' => $request->user(),
            'barangayProfiles' => $this->feedService->listBarangayProfiles($tenantId),
            'commentPreviewPost' => null,
        ]);
    }

    public function comments(Request $request, int $id): View
    {
        $user = $request->user();
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

        return view('community_feed::index', [
            'user' => $user,
            'barangayProfiles' => $this->feedService->listBarangayProfiles($user?->tenant_id),
            'commentPreviewPost' => app(CommunityFeedPostController::class)->formatPostForPage($post, (int) $user->id),
        ]);
    }

    public function skFedProfile(Request $request): View
    {
        return view('community_feed::sk-fed-profile', ['user' => $request->user()]);
    }

    public function createPost(Request $request): RedirectResponse
    {
        return redirect()->route('sk-fed-profile')->with('success', 'Post created successfully.');
    }

    public function barangayProfile(Request $request, string $slug): View
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id;
        $profile = $this->feedService->resolveBarangayProfile($slug, $tenantId);

        if ($profile === null) {
            abort(404);
        }

        $barangayId = $profile['id'] ?? null;
        $term = $this->feedService->resolveBarangayTerm($barangayId);
        $officials = $this->feedService->listOfficialsForBarangay($barangayId, $profile['logo_url'] ?? null);
        $formattedPosts = $this->loadFormattedBarangayPosts($barangayId, (int) $user->id);

        return view('community_feed::barangay-profile', [
            'user' => $user,
            'slug' => $slug,
            'name' => $profile['name'],
            'color' => $profile['color'],
            'profile' => $profile,
            'officials' => $officials,
            'officer_count' => count($officials),
            'post_count' => count($formattedPosts),
            'term_label' => $term['label'],
            'formattedPosts' => $formattedPosts,
            'commentPreviewPost' => null,
        ]);
    }

    public function barangayComments(Request $request, string $slug, int $id): View
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id;
        $profile = $this->feedService->resolveBarangayProfile($slug, $tenantId);

        if ($profile === null) {
            abort(404);
        }

        $barangayId = $profile['id'] ?? null;
        $post = Announcement::query()
            ->active()
            ->where('barangay_id', $barangayId)
            ->whereRaw('"is_federation_wide" = false')
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

        $term = $this->feedService->resolveBarangayTerm($barangayId);
        $officials = $this->feedService->listOfficialsForBarangay($barangayId, $profile['logo_url'] ?? null);
        $formattedPosts = $this->loadFormattedBarangayPosts($barangayId, (int) $user->id);

        return view('community_feed::barangay-profile', [
            'user' => $user,
            'slug' => $slug,
            'name' => $profile['name'],
            'color' => $profile['color'],
            'profile' => $profile,
            'officials' => $officials,
            'officer_count' => count($officials),
            'post_count' => count($formattedPosts),
            'term_label' => $term['label'],
            'formattedPosts' => $formattedPosts,
            'commentPreviewPost' => app(CommunityFeedPostController::class)->formatPostForPage($post, (int) $user->id),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadFormattedBarangayPosts(?int $barangayId, int $userId): array
    {
        if ($barangayId === null) {
            return [];
        }

        $formatter = app(CommunityFeedPostController::class);

        return Announcement::query()
            ->with([
                'barangay',
                'comments.user',
                'comments.reactions',
                'user',
                'images',
                'reactions.user',
            ])
            ->withCount('reactions')
            ->active()
            ->where('barangay_id', $barangayId)
            ->whereRaw('"is_federation_wide" = false')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Announcement $post) => $formatter->formatPostForPage($post, $userId))
            ->values()
            ->all();
    }
}
