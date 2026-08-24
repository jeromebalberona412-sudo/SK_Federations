<?php

namespace App\Services;

use App\Models\SkFederationsNotification;
use App\Modules\Accounts\Models\OfficialProfile;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SkFederationsNotificationService
{
    public const CATEGORY_COMMUNITY_FEED = 'community_feed';

    public const CATEGORY_BARANGAY_MONITORING = 'barangay_monitoring';

    public const CATEGORY_ABYIP = 'abyip';

    public const CATEGORY_GENERAL = 'general';

    /**
     * @return list<array{id: int, category: string, category_label: string, title: string, text: string, time: string, unread: bool, action_url: string|null}>
     */
    public function recentForUser(?User $user, int $limit = 5): array
    {
        if (! $user) {
            return [];
        }

        return $this->forUserQuery($user)
            ->limit($limit)
            ->get()
            ->map(fn (SkFederationsNotification $notification) => $this->formatNotification($notification))
            ->all();
    }

    /**
     * @return list<array{id: int, category: string, category_label: string, title: string, text: string, time: string, unread: bool, action_url: string|null}>
     */
    public function allForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return $this->forUserQuery($user)
            ->get()
            ->map(fn (SkFederationsNotification $notification) => $this->formatNotification($notification))
            ->all();
    }

    public function unreadCountForUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return $this->forUserQuery($user)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(User $user, int $notificationId): bool
    {
        $notification = SkFederationsNotification::query()
            ->where('user_id', $user->id)
            ->whereKey($notificationId)
            ->first();

        if (! $notification || $notification->read_at !== null) {
            return false;
        }

        $notification->update(['read_at' => now()]);

        return true;
    }

    public function markAllRead(User $user): int
    {
        return SkFederationsNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function notifyUser(
        User $user,
        string $category,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): SkFederationsNotification {
        return SkFederationsNotification::create([
            'user_id' => $user->id,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
        ]);
    }

    public function notifyAllFedUsers(
        string $category,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): void {
        foreach ($this->federationPortalUsers() as $user) {
            $this->notifyUser($user, $category, $title, $body, $actionUrl);
        }
    }

    /**
     * @param  list<int>  $excludeUserIds
     */
    public function notifyFederationPortalUsersExcept(
        array $excludeUserIds,
        string $category,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): void {
        $exclude = array_fill_keys(array_map('intval', $excludeUserIds), true);

        foreach ($this->federationPortalUsers() as $user) {
            if (isset($exclude[(int) $user->id])) {
                continue;
            }

            $this->notifyUser($user, $category, $title, $body, $actionUrl);
        }
    }

    public function notifyCommunityFeedLike(int $ownerUserId, string $reactorName, string $postLabel, int $postId): void
    {
        $owner = User::query()->find($ownerUserId);
        if (! $owner) {
            return;
        }

        $this->notifyUser(
            $owner,
            self::CATEGORY_COMMUNITY_FEED,
            "{$reactorName} liked your post",
            $postLabel,
            '/community-feed',
        );
    }

    public function notifyCommunityFeedComment(
        int $ownerUserId,
        string $commenterName,
        string $postLabel,
        int $postId,
        ?string $commentBody = null,
    ): void {
        $owner = User::query()->find($ownerUserId);
        if (! $owner) {
            return;
        }

        $preview = trim((string) $commentBody) !== ''
            ? Str::limit(trim((string) $commentBody), 160)
            : $postLabel;

        $this->notifyUser(
            $owner,
            self::CATEGORY_COMMUNITY_FEED,
            "{$commenterName} commented on your post",
            $preview,
            '/community-feed',
        );
    }

    public function postLabel(?string $title, ?string $body): string
    {
        $title = trim((string) $title);
        if ($title !== '') {
            return Str::limit($title, 80);
        }

        return Str::limit(trim(strip_tags((string) $body)), 80, '…') ?: 'your post';
    }

    public function notifyAbyipSubmission(string $barangayName, int $fiscalYear, ?string $barangaySlug = null): void
    {
        $slug = $barangaySlug ?: $this->resolveBarangaySlug($barangayName);
        $actionUrl = $slug ? "/barangay-monitoring/{$slug}" : '/barangay-monitoring';

        $this->notifyAllFedUsers(
            self::CATEGORY_ABYIP,
            'New ABYIP Submission',
            "Barangay {$barangayName} submitted their ABYIP for CY {$fiscalYear}.",
            $actionUrl,
        );
    }

    private function resolveBarangaySlug(string $barangayName): ?string
    {
        $map = [
            'alipit' => 'Alipit',
            'bagumbayan' => 'Bagumbayan',
            'calios' => 'Calios',
            'duhat' => 'Duhat',
            'gatid' => 'Gatid',
            'jasaan' => 'Jasaan',
            'labuin' => 'Labuin',
            'malinao' => 'Malinao',
            'oogong' => 'Oogong',
            'pagsawitan' => 'Pagsawitan',
            'palasan' => 'Palasan',
            'patimbao' => 'Patimbao',
            'brgy-1-poblacion' => 'Poblacion I',
            'brgy-2-poblacion' => 'Poblacion II',
            'brgy-3-poblacion' => 'Poblacion III',
            'brgy-4-poblacion' => 'Poblacion IV',
            'brgy-5-poblacion' => 'Poblacion V',
            'san-jose' => 'San Jose',
            'san-juan' => 'San Juan',
            'san-pablo-norte' => 'San Pablo Norte',
            'san-pablo-sur' => 'San Pablo Sur',
            'santisima-cruz' => 'Santisima Cruz',
            'santo-angel-central' => 'Santo Angel Central',
            'santo-angel-norte' => 'Santo Angel Norte',
            'santo-angel-sur' => 'Santo Angel Sur',
        ];

        foreach ($map as $slug => $name) {
            if (strcasecmp($name, $barangayName) === 0) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function federationPortalUsers()
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->where('role', User::ROLE_SK_FED)
                    ->orWhere(function ($nested) {
                        $nested->where('role', User::ROLE_SK_OFFICIAL)
                            ->whereHas('officialProfile', function ($profile) {
                                $profile->whereIn('federation_position', OfficialProfile::FEDERATION_PORTAL_ACCESS_POSITIONS);
                            });

                        if (Schema::hasColumn('users', 'has_federation_access')) {
                            $nested->whereRaw('has_federation_access IS TRUE');
                        }
                    });
            })
            ->with('officialProfile')
            ->get()
            ->filter(fn (User $user) => $user->canAccessFederationPortal())
            ->unique('id')
            ->values();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<SkFederationsNotification>
     */
    private function forUserQuery(User $user)
    {
        return SkFederationsNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');
    }

    /**
     * @return array{id: int, category: string, category_label: string, title: string, text: string, time: string, unread: bool, action_url: string|null}
     */
    private function formatNotification(SkFederationsNotification $notification): array
    {
        return [
            'id' => (int) $notification->id,
            'category' => $notification->category,
            'category_label' => $this->categoryLabel($notification->category),
            'title' => $notification->title,
            'text' => $notification->body,
            'time' => $this->formatTime($notification->created_at),
            'unread' => $notification->isUnread(),
            'action_url' => $notification->action_url,
        ];
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            self::CATEGORY_COMMUNITY_FEED => 'Community Feed',
            self::CATEGORY_ABYIP => 'ABYIP',
            self::CATEGORY_BARANGAY_MONITORING => 'Barangay Monitoring',
            default => 'General',
        };
    }

    private function formatTime(?Carbon $timestamp): string
    {
        if (! $timestamp) {
            return '—';
        }

        return $timestamp->format('g:i A').' · '.$timestamp->format('M j, Y');
    }
}
