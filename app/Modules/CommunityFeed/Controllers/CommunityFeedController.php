<?php

namespace App\Modules\CommunityFeed\Controllers;

use App\Modules\CommunityFeed\Services\CommunityFeedService;
use App\Modules\Shared\Controllers\Controller;
use App\Modules\Shared\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            'feedYearsWithPosts' => $this->yearsWithFederationPosts(),
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
                'videos',
                'reactions.user',
            ])
            ->withCount('reactions')
            ->findOrFail($id);

        return view('community_feed::index', [
            'user' => $user,
            'barangayProfiles' => $this->feedService->listBarangayProfiles($user?->tenant_id),
            'commentPreviewPost' => app(CommunityFeedPostController::class)->formatPostForPage($post, (int) $user->id),
            'feedYearsWithPosts' => $this->yearsWithFederationPosts(),
        ]);
    }

    public function publicShow(string $token): View|Response
    {
        $token = strtoupper(trim($token));

        if (! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $token)) {
            return response()
                ->view('community_feed::public-post', [
                    'available' => false,
                    'post' => null,
                ], 404);
        }

        $post = Announcement::query()
            ->whereRaw('UPPER(share_token) = ?', [$token])
            ->with(['barangay', 'images', 'videos'])
            ->first();

        if ($post === null || ! $post->isShareable()) {
            return response()
                ->view('community_feed::public-post', [
                    'available' => false,
                    'post' => null,
                ], 404);
        }

        return view('community_feed::public-post', [
            'available' => true,
            'post' => app(CommunityFeedPostController::class)->formatPublicPost($post),
        ]);
    }

    public function skFedProfile(Request $request): View
    {
        $user = $request->user();
        $formattedPosts = $this->loadFormattedFederationPosts((int) $user->id);
        $officerCount = $this->countFederationOfficers((int) ($user->tenant_id ?? 0));

        return view('community_feed::sk-fed-profile', [
            'user' => $user,
            'formattedPosts' => $formattedPosts,
            'post_count' => count($formattedPosts),
            'officer_count' => $officerCount,
            'term_label' => $this->federationTermLabel((int) ($user->tenant_id ?? 0)),
            'commentPreviewPost' => null,
            'avatar' => asset('Images/SK_OnePortal_logo.png'),
        ]);
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
                'videos',
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
    private function loadFormattedFederationPosts(int $userId): array
    {
        $formatter = app(CommunityFeedPostController::class);

        return Announcement::query()
            ->with([
                'barangay',
                'comments.user',
                'comments.reactions',
                'user',
                'images',
                'videos',
                'reactions.user',
            ])
            ->withCount('reactions')
            ->active()
            ->whereRaw('"is_federation_wide" = true')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Announcement $post) => $formatter->formatPostForPage($post, $userId))
            ->values()
            ->all();
    }

    private function countFederationOfficers(int $tenantId): int
    {
        if ($tenantId <= 0) {
            return 0;
        }

        return \App\Modules\Shared\Models\User::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('role', \App\Modules\Shared\Models\User::ROLE_SK_FED)
                    ->orWhere(function ($officials) {
                        $officials->where('role', \App\Modules\Shared\Models\User::ROLE_SK_OFFICIAL)
                            // pgsql boolean columns reject integer 1 — use IS TRUE.
                            ->whereRaw('has_federation_access IS TRUE');
                    });
            })
            ->count();
    }

    private function federationTermLabel(int $tenantId): string
    {
        try {
            // Prefer President term; fall back to leadership defaults so SK Term is never blank.
            $terms = \App\Modules\Accounts\Support\SkOfficialTermDates::presidentTermDates($tenantId)
                ?? \App\Modules\Accounts\Support\SkOfficialTermDates::leadershipDefaults($tenantId);

            if (empty($terms['term_start']) || empty($terms['term_end'])) {
                return '—';
            }

            $start = \Illuminate\Support\Carbon::parse($terms['term_start'])->format('Y');
            $end = \Illuminate\Support\Carbon::parse($terms['term_end'])->format('Y');

            return $start.'–'.$end;
        } catch (\Throwable) {
            return '—';
        }
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
                'videos',
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

    /**
     * @return list<int>
     */
    private function yearsWithFederationPosts(): array
    {
        return Announcement::query()
            ->active()
            ->whereRaw('"is_federation_wide" = true')
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM created_at)::integer as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(static fn ($year): int => (int) $year)
            ->filter(static fn (int $year): bool => $year >= 2000 && $year <= 2100)
            ->values()
            ->all();
    }
}
