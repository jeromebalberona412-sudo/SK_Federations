<?php

namespace App\Services;

use App\Modules\Shared\Models\Announcement;
use App\Modules\Shared\Models\AnnouncementComment;
use App\Modules\Shared\Models\AnnouncementReaction;
use App\Modules\Shared\Models\User;

class CommunityFeedAvatarService
{
    public const HEADER_COLOR = '213F99';

    public function __construct(private readonly BarangayLogoUrlService $barangayLogos) {}

    public function federationDefault(): string
    {
        return asset('Images/SK_Fed_profile.png');
    }

    public function barangayLogo(?int $barangayId): ?string
    {
        if (! $barangayId) {
            return null;
        }

        return $this->barangayLogos->resolve($barangayId);
    }

    public function resolveForUser(?User $user, ?string $userType = null, ?string $fallbackName = null): string
    {
        $type = $userType ?? $user?->role;

        if ($type === 'kabataan') {
            $photo = $this->absoluteProfilePhoto($user);
            if ($photo) {
                return $photo;
            }
        }

        if ($type === 'sk_official' && $user?->barangay_id) {
            $logo = $this->barangayLogos->resolve((int) $user->barangay_id);
            if ($logo) {
                return $logo;
            }
        }

        if ($type === 'sk_fed' || $user?->role === User::ROLE_SK_FED) {
            return $this->federationDefault();
        }

        $photo = $this->absoluteProfilePhoto($user);
        if ($photo) {
            return $photo;
        }

        return $this->uiAvatar($user?->name ?? $fallbackName ?? 'Member');
    }

    private function absoluteProfilePhoto(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $raw = trim((string) ($user->profile_image_url ?? ''));
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return $raw;
        }

        if (str_starts_with($raw, '/storage/') || str_starts_with($raw, 'storage/')) {
            return url('/'.ltrim($raw, '/'));
        }

        return $raw;
    }

    public function resolveForPost(Announcement $post): string
    {
        if (! $post->is_federation_wide && $post->barangay_id) {
            $logo = $this->barangayLogos->resolve((int) $post->barangay_id);
            if ($logo) {
                return $logo;
            }
        }

        if ($post->relationLoaded('user') && $post->user) {
            return $this->resolveForUser(
                $post->user,
                $post->is_federation_wide ? 'sk_fed' : 'sk_official',
                $post->user->name,
            );
        }

        if ($post->is_federation_wide) {
            return $this->federationDefault();
        }

        return $this->uiAvatar($post->barangay?->name ?? 'SK');
    }

    public function resolveForComment(AnnouncementComment $comment): string
    {
        if ($comment->relationLoaded('user') && $comment->user) {
            return $this->resolveForUser($comment->user, $comment->user_type, $comment->author_name);
        }

        if ($comment->user_type === 'sk_fed') {
            return $this->federationDefault();
        }

        return $this->uiAvatar($comment->author_name ?? 'Member');
    }

    public function resolveForReaction(AnnouncementReaction $reaction): string
    {
        if ($reaction->relationLoaded('user') && $reaction->user) {
            return $this->resolveForUser($reaction->user, $reaction->user_type);
        }

        return $this->uiAvatar('Member');
    }

    public function uiAvatar(string $name): string
    {
        return 'https://ui-avatars.com/api/?name='.urlencode($name)
            .'&background='.self::HEADER_COLOR.'&color=fff&size=80';
    }
}
