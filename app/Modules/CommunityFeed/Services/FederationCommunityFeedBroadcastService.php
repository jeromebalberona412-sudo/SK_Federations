<?php

namespace App\Modules\CommunityFeed\Services;

use App\Models\CommunityFeedEmailSend;
use App\Models\KabataanRegistration;
use App\Modules\Accounts\Support\PortalAppUrl;
use App\Modules\CommunityFeed\Notifications\FederationCommunityFeedPostPublishedNotification;
use App\Modules\Shared\Models\Announcement;
use App\Modules\Shared\Models\User;
use App\Services\InvalidEmailService;
use App\Services\SkFederationsNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class FederationCommunityFeedBroadcastService
{
    public function __construct(
        private readonly SkFederationsNotificationService $federationNotifications,
        private readonly InvalidEmailService $invalidEmails,
    ) {}

    public function broadcastInApp(Announcement $post, int $authorUserId): void
    {
        if ($post->shouldNotifyKabataan()) {
            try {
                $this->notifyKabataanInApp($post);
            } catch (Throwable $exception) {
                Log::error('[FederationCommunityFeedBroadcast] Kabataan in-app failed.', [
                    'community_feed_id' => $post->id,
                    'error' => $exception->getMessage(),
                ]);
                report($exception);
            }
        }
        if ($post->shouldNotifyOfficials()) {
            try {
                $this->notifyOfficialsInApp($post);
                $this->bumpOfficialsCommunityFeedCache();
            } catch (Throwable $exception) {
                Log::error('[FederationCommunityFeedBroadcast] Officials in-app failed.', [
                    'community_feed_id' => $post->id,
                    'error' => $exception->getMessage(),
                ]);
                report($exception);
            }
        }
        if ($post->shouldNotifyFederationPortal()) {
            try {
                $this->notifyFederationPortalUsersInApp($post, $authorUserId);
            } catch (Throwable $exception) {
                Log::error('[FederationCommunityFeedBroadcast] Federation portal in-app failed.', [
                    'community_feed_id' => $post->id,
                    'error' => $exception->getMessage(),
                ]);
                report($exception);
            }
        }
    }

    public function broadcastEmail(Announcement $post): void
    {
        if (! $post->email_notifications_enabled) {
            return;
        }

        $kabataanUrl = $this->buildKabataanPostUrl((int) $post->id);
        $officialsUrl = $this->buildOfficialsPostUrl((int) $post->id);

        if ($post->shouldNotifyKabataan()) {
            foreach ($this->eligibleKabataanEmailRecipients() as $recipient) {
                $this->sendEmailToRecipient(
                    $post,
                    $recipient['email'],
                    $recipient['name'],
                    $recipient['user_id'],
                    $recipient['kabataan_registration_id'],
                    $kabataanUrl,
                );
            }
        }

        if ($post->shouldNotifyOfficials()) {
            foreach ($this->eligibleOfficialEmailRecipients() as $recipient) {
                $this->sendEmailToRecipient(
                    $post,
                    $recipient['email'],
                    $recipient['name'],
                    $recipient['user_id'],
                    null,
                    $officialsUrl,
                );
            }
        }

        if ($post->shouldNotifyFederationPortal()) {
            foreach ($this->eligibleFederationPortalEmailRecipients() as $recipient) {
                $this->sendEmailToRecipient(
                    $post,
                    $recipient['email'],
                    $recipient['name'],
                    $recipient['user_id'],
                    null,
                    url('/community-feed/comments/'.(int) $post->id),
                );
            }
        }
    }

    private function notifyKabataanInApp(Announcement $post): void
    {
        if (! Schema::hasTable('kabataan_notifications')) {
            Log::warning('[FederationCommunityFeedBroadcast] kabataan_notifications table missing; skipping Kabataan bells.', [
                'community_feed_id' => $post->id,
            ]);

            return;
        }

        $userIds = $this->eligibleKabataanUserIds();
        if ($userIds === []) {
            Log::info('[FederationCommunityFeedBroadcast] No eligible Kabataan users for in-app notify.', [
                'community_feed_id' => $post->id,
            ]);

            return;
        }

        $title = trim((string) ($post->title ?? ''));
        $preview = Str::limit(trim(strip_tags((string) $post->body)), 120, '…');
        $body = 'SK Federation published a new Community Feed post.';
        if ($title !== '') {
            $body .= ' '.$title;
        } elseif ($preview !== '') {
            $body .= ' '.$preview;
        }

        $now = now();
        $actionUrl = '/dashboard/comments/'.(int) $post->id;
        $rows = [];
        foreach ($userIds as $userId) {
            $rows[] = [
                'user_id' => $userId,
                'category' => 'announcement',
                'title' => 'New SK Federation Community Feed post',
                'body' => $body,
                'action_url' => $actionUrl,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('kabataan_notifications')->insert($chunk);
            }
        } catch (Throwable $exception) {
            Log::error('[FederationCommunityFeedBroadcast] Kabataan in-app insert failed.', [
                'community_feed_id' => $post->id,
                'recipients' => count($rows),
                'error' => $exception->getMessage(),
            ]);
            report($exception);
        }
    }

    private function notifyOfficialsInApp(Announcement $post): void
    {
        if (! Schema::hasTable('sk_officials_notifications')) {
            Log::warning('[FederationCommunityFeedBroadcast] sk_officials_notifications table missing; skipping Officials bells.', [
                'community_feed_id' => $post->id,
            ]);

            return;
        }

        $officialIds = User::query()
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->where('status', User::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($officialIds === []) {
            return;
        }

        $postLabel = $this->federationNotifications->postLabel($post->title, $post->body);
        $now = now();
        $actionUrl = '/community-feed/comments/'.(int) $post->id;
        $rows = [];

        foreach ($officialIds as $officialId) {
            $rows[] = [
                'user_id' => $officialId,
                'category' => 'announcement',
                'title' => 'New SK Federation Community Feed post',
                'body' => $postLabel,
                'action_url' => $actionUrl,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('sk_officials_notifications')->insert($chunk);
            }
        } catch (Throwable $exception) {
            Log::error('[FederationCommunityFeedBroadcast] SK Officials in-app insert failed.', [
                'community_feed_id' => $post->id,
                'recipients' => count($rows),
                'error' => $exception->getMessage(),
            ]);
            report($exception);
        }
    }

    private function notifyFederationPortalUsersInApp(Announcement $post, int $authorUserId): void
    {
        $postLabel = $this->federationNotifications->postLabel($post->title, $post->body);

        $this->federationNotifications->notifyFederationPortalUsersCommunityFeedActivity(
            [$authorUserId],
            (int) $post->id,
            'New SK Federation Community Feed post',
            $postLabel,
            'comments',
        );
    }

    /**
     * @return list<int>
     */
    private function eligibleKabataanUserIds(): array
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return [];
        }

        return $this->eligibleKabataanRegistrationsQuery()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{
     *     email: string,
     *     name: string,
     *     user_id: int,
     *     kabataan_registration_id: int
     * }>
     */
    private function eligibleKabataanEmailRecipients(): Collection
    {
        if (! Schema::hasTable('kabataan_registrations')) {
            return collect();
        }

        return $this->eligibleKabataanRegistrationsQuery()
            ->with(['user' => fn ($query) => $query->select('id', 'email', 'name', 'status', 'deleted_at')])
            ->get()
            ->map(function (KabataanRegistration $registration) {
                $email = $this->resolveKabataanEmail($registration);
                if ($email === null || (int) ($registration->user_id ?? 0) <= 0) {
                    return null;
                }

                $name = trim((string) $registration->full_name);
                if ($name === '' || $name === '—') {
                    $name = trim((string) ($registration->user?->name ?? ''));
                }

                return [
                    'email' => $email,
                    'name' => $name !== '' ? $name : 'Kabataan Member',
                    'user_id' => (int) $registration->user_id,
                    'kabataan_registration_id' => (int) $registration->id,
                ];
            })
            ->filter()
            ->unique(fn (array $recipient) => strtolower($recipient['email']))
            ->values();
    }

    /**
     * @return Collection<int, array{email: string, name: string, user_id: int}>
     */
    private function eligibleOfficialEmailRecipients(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->where('status', User::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['id', 'email', 'name'])
            ->map(function (User $official) {
                $email = $this->resolveValidEmail((string) $official->email);
                if ($email === null) {
                    return null;
                }

                $name = trim((string) $official->name);

                return [
                    'email' => $email,
                    'name' => $name !== '' ? $name : 'SK Official',
                    'user_id' => (int) $official->id,
                ];
            })
            ->filter()
            ->unique(fn (array $recipient) => strtolower($recipient['email']))
            ->values();
    }

    /**
     * @return Collection<int, array{email: string, name: string, user_id: int}>
     */
    private function eligibleFederationPortalEmailRecipients(): Collection
    {
        return $this->federationNotifications
            ->federationPortalUsersForBroadcast()
            ->map(function (User $user) {
                $email = $this->resolveValidEmail((string) ($user->email ?? ''));
                if ($email === null) {
                    return null;
                }

                $name = trim((string) $user->name);

                return [
                    'email' => $email,
                    'name' => $name !== '' ? $name : 'SK Federation',
                    'user_id' => (int) $user->id,
                ];
            })
            ->filter()
            ->unique(fn (array $recipient) => strtolower($recipient['email']))
            ->values();
    }

    /**
     * @return Builder<KabataanRegistration>
     */
    private function eligibleKabataanRegistrationsQuery(): Builder
    {
        $query = KabataanRegistration::query()
            ->whereNull('deleted_at')
            ->whereNotNull('user_id')
            ->whereIn('status', ['active', 'email_verified', 'password_set']);

        if (Schema::hasColumn('kabataan_registrations', 'archived_at')) {
            $query->whereNull('archived_at');
        }

        if (Schema::hasColumn('kabataan_registrations', 'evaluation_status')) {
            $query->where(function (Builder $evaluationQuery) {
                $evaluationQuery->whereNull('evaluation_status')
                    ->orWhereIn('evaluation_status', ['active', 'Auto Approved', 'ID Verified']);
            });
        }

        $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('status', User::STATUS_ACTIVE);
            if (Schema::hasColumn('users', 'deleted_at')) {
                $userQuery->whereNull('deleted_at');
            }
        });

        return $query;
    }

    private function sendEmailToRecipient(
        Announcement $post,
        string $email,
        string $name,
        ?int $userId,
        ?int $kabataanRegistrationId,
        string $postUrl,
    ): void {
        $normalized = $this->invalidEmails->normalize($email);
        $invalidCheck = $this->invalidEmails->checkBeforeSending($normalized);
        if (! $invalidCheck['allowed']) {
            return;
        }

        $claim = $this->claimRecipientSlot(
            (int) $post->id,
            $normalized,
            $kabataanRegistrationId,
            $userId,
        );

        if ($claim === null) {
            return;
        }

        try {
            Notification::route('mail', $normalized)
                ->notify(new FederationCommunityFeedPostPublishedNotification(
                    $post,
                    $name,
                    $postUrl,
                ));

            $claim->update(['sent_at' => now()]);
        } catch (Throwable $exception) {
            $claim->delete();
            $this->invalidEmails->recordFailureFromException($normalized, $exception);
            Log::warning('[FederationCommunityFeedBroadcast] Failed to email recipient.', [
                'community_feed_id' => $post->id,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
            report($exception);
        }
    }

    private function claimRecipientSlot(
        int $communityFeedId,
        string $normalizedEmail,
        ?int $kabataanRegistrationId,
        ?int $userId,
    ): ?CommunityFeedEmailSend {
        if (! Schema::hasTable('community_feed_email_sends')) {
            return new CommunityFeedEmailSend([
                'community_feed_id' => $communityFeedId,
                'recipient_email' => $normalizedEmail,
                'kabataan_registration_id' => $kabataanRegistrationId,
                'user_id' => $userId,
                'sent_at' => null,
            ]);
        }

        try {
            return CommunityFeedEmailSend::query()->create([
                'community_feed_id' => $communityFeedId,
                'recipient_email' => $normalizedEmail,
                'kabataan_registration_id' => $kabataanRegistrationId,
                'user_id' => $userId,
                'sent_at' => null,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveKabataanEmail(KabataanRegistration $registration): ?string
    {
        $candidates = [
            trim((string) ($registration->user?->email ?? '')),
            trim((string) ($registration->email ?? '')),
        ];

        foreach ($candidates as $candidate) {
            $email = $this->resolveValidEmail($candidate);
            if ($email !== null) {
                return $email;
            }
        }

        return null;
    }

    private function resolveValidEmail(string $email): ?string
    {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $this->invalidEmails->normalize($email);
    }

    private function bumpOfficialsCommunityFeedCache(): void
    {
        try {
            $key = 'skoff:pagever:community-feed:global';
            Cache::forever($key, ((int) Cache::get($key, 1)) + 1);
        } catch (Throwable $e) {
            Log::warning('Unable to bump Officials community feed cache.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildKabataanPostUrl(int $postId): string
    {
        $base = PortalAppUrl::preferAbsolute([
            config('services.kabataan_app_url'),
            config('app.public_url'),
        ]) ?? '';

        if ($base === '') {
            Log::warning('KABATAAN_APP_URL is missing or localhost; Federation→Kabataan email links may be wrong in production.');

            $fallback = PortalAppUrl::selfApp();

            return PortalAppUrl::join($fallback, '/dashboard/comments/'.$postId);
        }

        return PortalAppUrl::join($base, '/dashboard/comments/'.$postId);
    }

    private function buildOfficialsPostUrl(int $postId): string
    {
        $base = PortalAppUrl::preferAbsolute([
            config('services.sk_officials_app_url'),
        ]) ?? '';
        $path = '/community-feed/comments/'.$postId;

        if ($base === '') {
            Log::warning('SK_OFFICIALS_APP_URL is empty or localhost; Federation→Officials email links may open the wrong app.');

            return PortalAppUrl::join(
                PortalAppUrl::selfApp(),
                $path
            );
        }

        return PortalAppUrl::join($base, $path);
    }
}
