<?php

namespace App\Modules\Communications\Services;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Authentication\Services\BootstrapSkFedAdminService;
use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Models\ConversationParticipant;
use App\Modules\Communications\Models\Message;
use App\Modules\Communications\Models\MessageHide;
use App\Modules\Shared\Models\User;
use App\Services\CommunityFeedAvatarService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ConversationService
{
    public function __construct(
        protected ParticipantTypeResolver $types,
        protected CommunityFeedAvatarService $avatars,
        protected DefaultGroupChatService $defaultGroups,
    ) {}

    public function isParticipant(Conversation $conversation, Authenticatable $user, ?string $userType = null): bool
    {
        // Groups (and shared Official/Federation accounts) key membership by stable user id.
        if ($conversation->isGroup()) {
            return ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', (int) $user->id)
                ->exists();
        }

        $userType ??= $this->types->portalType();

        return ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', (int) $user->id)
            ->where('user_type', $userType)
            ->exists();
    }

    public function findOrCreatePrivate(Authenticatable $authUser, int $otherUserId): Conversation
    {
        $authType = $this->types->portalType();
        $other = User::query()->findOrFail($otherUserId);

        if ((int) $other->id === (int) $authUser->id) {
            abort(422, 'Cannot start a conversation with yourself.');
        }

        if (! $this->canMessage($authUser, $other)) {
            abort(403, 'You are not allowed to message this account.');
        }

        $otherType = $this->types->fromUser($other);

        $existingId = $this->findPrivateConversationId(
            (int) $authUser->id,
            $authType,
            (int) $other->id,
            $otherType
        );

        if ($existingId) {
            return Conversation::query()->findOrFail($existingId);
        }

        return DB::transaction(function () use ($authUser, $authType, $other, $otherType) {
            $conversation = Conversation::query()->create([
                'conversation_type' => 'private',
            ]);

            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => (int) $authUser->id,
                'user_type' => $authType,
                'joined_at' => now(),
            ]);

            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => (int) $other->id,
                'user_type' => $otherType,
                'joined_at' => now(),
            ]);

            return $conversation;
        });
    }

    public function listForUser(Authenticatable $user, ?string $search = null): Collection
    {
        $this->defaultGroups->ensureForUser($user);

        $userType = $this->types->portalType();
        $userId = (int) $user->id;

        $conversationIds = ConversationParticipant::query()
            ->where('user_id', $userId)
            ->pluck('conversation_id')
            ->unique()
            ->values();

        if ($conversationIds->isEmpty()) {
            return collect();
        }

        $conversations = Conversation::query()
            ->whereIn('id', $conversationIds)
            ->with(['participants.user'])
            ->get();

        $hiddenIds = MessageHide::query()
            ->where('user_id', $userId)
            ->where('user_type', $userType)
            ->pluck('message_id');

        $lastMessages = Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->when($hiddenIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $hiddenIds))
            ->orderByDesc('id')
            ->get()
            ->unique('conversation_id')
            ->keyBy('conversation_id');

        $myParticipants = ConversationParticipant::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('conversation_id');

        $items = $conversations->map(function (Conversation $conversation) use ($user, $userId, $userType, $lastMessages, $myParticipants) {
            $last = $lastMessages->get($conversation->id);
            $mine = $myParticipants->get($conversation->id);
            $unread = $this->unreadCountForParticipant($conversation->id, $mine, $userId, $userType);
            $lastBody = $last?->body;
            if ($last && $last->deleted_for_all_at !== null) {
                $lastBody = 'This message was deleted';
            } elseif ($last && (! $lastBody || trim((string) $lastBody) === '')) {
                $lastBody = match ((string) $last->message_type) {
                    'image' => 'Sent a photo',
                    'file' => 'Sent a file',
                    default => $lastBody,
                };
            } elseif ($last && $last->deleted_for_all_at === null && in_array((string) $last->message_type, ['image', 'file'], true) && is_string($lastBody) && trim($lastBody) !== '') {
                $lastBody = ((string) $last->message_type === 'image' ? '📷 ' : '📎 ').$lastBody;
            }

            $payload = [
                'id' => $conversation->id,
                'conversation_type' => $conversation->conversation_type,
                'name' => $conversation->name,
                'default_group_type' => $conversation->default_group_type,
                'max_members' => $conversation->max_members,
                'member_count' => $conversation->participants->count(),
                'is_default_group' => $conversation->isDefaultGroup(),
                'can_rename' => $conversation->isDefaultGroup()
                    ? $this->defaultGroups->canRename($conversation, $user)
                    : false,
                'can_change_avatar' => $conversation->isDefaultGroup()
                    ? $this->defaultGroups->canChangeAvatar($conversation, $user)
                    : false,
                'other_user' => null,
                'last_message' => $last ? [
                    'id' => $last->id,
                    'body' => $lastBody,
                    'message_type' => $last->message_type,
                    'sender_id' => $last->sender_id,
                    'created_at' => optional($last->created_at)?->toIso8601String(),
                ] : null,
                'unread_count' => $unread,
                'updated_at' => optional($last?->created_at ?? $conversation->updated_at)?->toIso8601String(),
            ];

            if ($conversation->isGroup()) {
                $groupName = trim((string) ($conversation->name ?: 'Group chat'));
                $max = (int) ($conversation->max_members ?: 0);
                $count = (int) $payload['member_count'];
                $avatar = $this->defaultGroups->groupAvatarUrl($conversation);
                $payload['avatar_url'] = $avatar;
                $payload['other_user'] = [
                    'id' => null,
                    'name' => $groupName,
                    'user_type' => 'group',
                    'user_type_label' => $this->groupTypeLabel($conversation),
                    'position' => $max > 0 ? ($count.' / '.$max.' members') : ($count.' members'),
                    'barangay_name' => null,
                    'profile_image_url' => $avatar,
                    'online_status' => null,
                    'is_group' => true,
                ];
                // Empty GCs still appear in Chats with a clear preview.
                if ($payload['last_message'] === null) {
                    $payload['last_message'] = [
                        'id' => null,
                        'body' => 'No messages yet',
                        'message_type' => 'system',
                        'sender_id' => null,
                        'created_at' => optional($conversation->created_at)?->toIso8601String(),
                    ];
                }

                return $payload;
            }

            $other = $conversation->participants
                ->first(fn (ConversationParticipant $p) => ! ((int) $p->user_id === $userId && $p->user_type === $userType));

            if ($other?->user && $this->isHiddenFederationAdministrator($other->user)) {
                return null;
            }

            $payload['other_user'] = $this->serializeUser($other?->user, $other?->user_type);

            return $payload;
        })->filter()->values();

        if ($search !== null && trim($search) !== '') {
            $q = mb_strtolower(trim($search));
            $items = $items->filter(function (array $item) use ($q) {
                $name = mb_strtolower((string) ($item['other_user']['name'] ?? $item['name'] ?? ''));

                return str_contains($name, $q);
            })->values();
        }

        return $items->sort(function (array $a, array $b): int {
            $aDefault = ($a['is_default_group'] ?? false) ? 0 : 1;
            $bDefault = ($b['is_default_group'] ?? false) ? 0 : 1;
            if ($aDefault !== $bDefault) {
                return $aDefault <=> $bDefault;
            }

            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        })->values();
    }

    public function groupTypeLabel(Conversation $conversation): string
    {
        return match ((string) $conversation->default_group_type) {
            DefaultGroupChatService::TYPE_SK_OFFICIALS => 'SK Officials group',
            DefaultGroupChatService::TYPE_SK_FEDERATIONS => 'SK Federations group',
            default => 'Group chat',
        };
    }

    /**
     * Header Chats popover: existing conversations first, then all messageable
     * SK Officials and SK Federation members (even when no conversation exists yet).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function headerInboxForUser(Authenticatable $user): Collection
    {
        $conversations = $this->listForUser($user);
        $seenPeerIds = [];

        foreach ($conversations as $item) {
            $peerId = (int) ($item['other_user']['id'] ?? 0);
            if ($peerId > 0) {
                $seenPeerIds[$peerId] = true;
            }
        }

        $directoryUsers = collect($this->directory($user, 'officials')['users'] ?? [])
            ->concat(collect($this->directory($user, 'federation')['users'] ?? []))
            ->unique(fn (array $peer) => (int) ($peer['id'] ?? 0))
            ->values();

        $pending = $directoryUsers
            ->filter(function (array $peer) use ($seenPeerIds): bool {
                $peerId = (int) ($peer['id'] ?? 0);

                return $peerId > 0 && ! isset($seenPeerIds[$peerId]);
            })
            ->map(function (array $peer): array {
                return [
                    'id' => null,
                    'conversation_type' => 'private',
                    'other_user' => $peer,
                    'last_message' => null,
                    'unread_count' => 0,
                    'updated_at' => null,
                ];
            })
            ->sortBy(fn (array $item) => mb_strtolower((string) ($item['other_user']['name'] ?? '')))
            ->values();

        return $conversations->concat($pending)->values();
    }

    public function showPayload(Conversation $conversation, Authenticatable $user): array
    {
        $userType = $this->types->portalType();
        $userId = (int) $user->id;

        $conversation->load(['participants.user']);

        if ($conversation->isGroup()) {
            $memberCount = $conversation->participants->count();
            $max = (int) ($conversation->max_members ?: 0);
            $groupName = trim((string) ($conversation->name ?: 'Group chat'));
            $avatar = $this->defaultGroups->groupAvatarUrl($conversation);

            return [
                'id' => $conversation->id,
                'conversation_type' => $conversation->conversation_type,
                'name' => $groupName,
                'default_group_type' => $conversation->default_group_type,
                'max_members' => $max ?: null,
                'member_count' => $memberCount,
                'is_default_group' => $conversation->isDefaultGroup(),
                'can_rename' => $this->defaultGroups->canRename($conversation, $user),
                'can_change_avatar' => $this->defaultGroups->canChangeAvatar($conversation, $user),
                'call_max_participants' => (int) config('communications.call_max_participants', 8),
                'avatar_url' => $avatar,
                'other_user' => [
                    'id' => null,
                    'name' => $groupName,
                    'user_type' => 'group',
                    'user_type_label' => $this->groupTypeLabel($conversation),
                    'position' => $max > 0 ? ($memberCount.' / '.$max.' members') : ($memberCount.' members'),
                    'barangay_name' => null,
                    'profile_image_url' => $avatar,
                    'online_status' => null,
                    'is_group' => true,
                ],
                'members' => $this->defaultGroups->serializeMembers($conversation, $this),
            ];
        }

        $other = $conversation->participants
            ->first(fn (ConversationParticipant $p) => ! ((int) $p->user_id === $userId && $p->user_type === $userType));

        return [
            'id' => $conversation->id,
            'conversation_type' => $conversation->conversation_type,
            'name' => null,
            'default_group_type' => null,
            'max_members' => null,
            'member_count' => $conversation->participants->count(),
            'is_default_group' => false,
            'can_rename' => false,
            'can_change_avatar' => false,
            'call_max_participants' => (int) config('communications.call_max_participants', 8),
            'other_user' => $this->serializeUser($other?->user, $other?->user_type),
            'members' => [],
        ];
    }

    public function markRead(Conversation $conversation, Authenticatable $user): void
    {
        $userType = $this->types->portalType();
        $userId = (int) $user->id;

        $latestMessageId = (int) (Message::query()
            ->where('conversation_id', $conversation->id)
            ->max('id') ?? 0);

        $query = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId);

        if (! $conversation->isGroup()) {
            $query->where('user_type', $userType);
        }

        $payload = ['last_read_at' => now()];
        if (Schema::hasColumn('conversation_participants', 'last_read_message_id') && $latestMessageId > 0) {
            $payload['last_read_message_id'] = $latestMessageId;
        }

        $query->update($payload);
    }

    public function unreadTotalForUser(Authenticatable $user): int
    {
        $userType = $this->types->portalType();
        $userId = (int) $user->id;
        $hasMessageCursor = Schema::hasColumn('conversation_participants', 'last_read_message_id');

        $columns = ['conversation_id', 'last_read_at', 'user_type'];
        if ($hasMessageCursor) {
            $columns[] = 'last_read_message_id';
        }

        $participants = ConversationParticipant::query()
            ->where('user_id', $userId)
            ->where('user_type', $userType)
            ->get($columns);

        if ($participants->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($participants as $participant) {
            $total += $this->unreadCountForParticipant(
                (int) $participant->conversation_id,
                $participant,
                $userId,
                $userType
            );
        }

        return $total;
    }

    public function searchUsers(Authenticatable $authUser, string $query): Collection
    {
        $q = trim($query);
        if ($q === '') {
            return collect();
        }

        $limit = (int) config('communications.search_limit', 20);
        $viewerType = $this->types->portalType();
        $viewerBarangayId = (int) ($authUser->barangay_id ?? 0);

        $builder = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', (int) $authUser->id)
            ->where(function (Builder $inner) use ($q) {
                $inner->where('name', 'ilike', '%'.$q.'%')
                    ->orWhere('email', 'ilike', '%'.$q.'%');
            });

        // Narrow search by portal messaging rules (barangay-scoped where required).
        if ($viewerType === ParticipantTypeResolver::KABATAAN) {
            if ($viewerBarangayId <= 0) {
                return collect();
            }
            $builder->where('role', 'sk_official')
                ->where('barangay_id', $viewerBarangayId);
        } elseif ($viewerType === ParticipantTypeResolver::SK_OFFICIAL) {
            $builder->where(function (Builder $scope) use ($viewerBarangayId) {
                $scope->where('role', 'sk_fed')
                    ->orWhere('role', 'sk_official')
                    ->orWhere(function (Builder $kabataan) use ($viewerBarangayId) {
                        $kabataan->whereIn('role', ['kabataan', 'user']);
                        if ($viewerBarangayId > 0) {
                            $kabataan->where('barangay_id', $viewerBarangayId);
                        } else {
                            $kabataan->whereRaw('1 = 0');
                        }
                    });
            });
        } else {
            $builder->whereIn('role', $this->types->searchableRoles());
        }

        return $builder->orderBy('name')
            ->limit($limit)
            ->get()
            ->filter(fn (User $user) => $this->canMessage($authUser, $user))
            ->values()
            ->map(fn (User $user) => $this->serializeUser($user, $this->types->fromUser($user)));
    }

    /**
     * Federations directory listing for quick message/call.
     * Filters: chairpersons | officials | federation | kabataan
     *
     * @return array{users: Collection, groups: array<int, array{title: string, users: array}>}
     */
    public function directory(Authenticatable $authUser, string $filter = 'officials', ?int $barangayId = null): array
    {
        $filter = strtolower(trim($filter));
        if (! in_array($filter, ['chairpersons', 'officials', 'federation', 'kabataan'], true)) {
            $filter = 'officials';
        }

        $query = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('id', '!=', (int) $authUser->id);

        $tenantId = (int) ($authUser->tenant_id ?? 0);
        if ($tenantId > 0 && Schema::hasColumn('users', 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        $with = [];
        if (method_exists(User::class, 'barangay')) {
            $with[] = 'barangay';
        }
        if (method_exists(User::class, 'officialProfile')) {
            $with[] = 'officialProfile';
        }
        if ($with !== []) {
            $query->with($with);
        }

        if ($filter === 'federation') {
            $query->where(function (Builder $scope) {
                $scope->where('role', User::ROLE_SK_FED);

                $scope->orWhere(function (Builder $officials) {
                    $officials->where('role', User::ROLE_SK_OFFICIAL);

                    $officials->where(function (Builder $fed) {
                        if (Schema::hasColumn('users', 'has_federation_access')) {
                            $fed->whereRaw('has_federation_access IS TRUE');
                        }

                        if (method_exists(User::class, 'officialProfile')) {
                            $fed->orWhereHas('officialProfile', function (Builder $profile) {
                                $profile->whereNotNull('federation_position')
                                    ->where('federation_position', '!=', '')
                                    ->whereRaw('LOWER(TRIM(federation_position)) NOT IN (?, ?, ?, ?)', [
                                        'none', 'n/a', 'na', '-',
                                    ]);
                            });
                        }
                    });
                });
            });
        } elseif ($filter === 'kabataan') {
            // Kabataan / youth accounts across Santa Cruz (optionally by barangay).
            $query->whereIn('role', ['kabataan', User::ROLE_USER]);

            if ($barangayId) {
                $query->where('barangay_id', $barangayId);
            }
        } else {
            // All SK Officials across Santa Cruz (optionally by barangay / chairperson).
            $query->where('role', User::ROLE_SK_OFFICIAL);

            if ($barangayId) {
                $query->where('barangay_id', $barangayId);
            }

            if ($filter === 'chairpersons') {
                if (! method_exists(User::class, 'officialProfile')) {
                    return ['users' => collect(), 'groups' => []];
                }
                $query->whereHas('officialProfile', function (Builder $profile) {
                    $profile->whereIn('position', ['Chairperson', 'Chairman']);
                });
            }
        }

        $users = $query->orderBy('name')
            ->limit(500)
            ->get()
            ->filter(fn (User $user) => $this->canMessage($authUser, $user))
            ->values();

        $mapped = $users->map(fn (User $user) => $this->serializeUser($user, $this->types->fromUser($user)));

        $groups = [];
        if (in_array($filter, ['officials', 'chairpersons', 'kabataan'], true)) {
            $grouped = $mapped->groupBy(fn (array $row) => $row['barangay_name'] ?: 'No barangay');
            foreach ($grouped->sortKeys() as $title => $rows) {
                $groups[] = [
                    'title' => (string) $title,
                    'users' => $rows->values()->all(),
                ];
            }
        } else {
            $groups[] = [
                'title' => 'SK Federation',
                'users' => $mapped->values()->all(),
            ];
        }

        return [
            'users' => $mapped,
            'groups' => $groups,
        ];
    }

    /**
     * @return Collection<int, array{id:int,name:string}>
     */
    public function barangayOptions(): Collection
    {
        $barangayClass = Barangay::class;
        if (! class_exists($barangayClass)) {
            return collect();
        }

        return $barangayClass::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->values();
    }

    /**
     * Barangay messaging rules:
     * - Kabataan: only SK Officials of the same barangay
     * - SK Official: kabataan of same barangay, any SK Official, any SK Federation
     * - SK Federation: active kabataan / sk_official / sk_fed accounts
     */
    public function canMessage(Authenticatable $from, User $to): bool
    {
        if ((int) $from->id === (int) $to->id) {
            return false;
        }

        if (strtoupper((string) $to->status) !== User::STATUS_ACTIVE) {
            return false;
        }

        // Never surface the bootstrap SK Federation Administrator in chats.
        if ($this->isHiddenFederationAdministrator($to)) {
            return false;
        }

        $toType = $this->types->normalizeSearchRole((string) $to->role);
        if ($toType === null) {
            return false;
        }

        $fromType = $this->types->portalType();
        $fromBarangayId = (int) ($from->barangay_id ?? 0);
        $toBarangayId = (int) ($to->barangay_id ?? 0);

        return match ($fromType) {
            ParticipantTypeResolver::KABATAAN => $toType === ParticipantTypeResolver::SK_OFFICIAL
                && ($fromBarangayId <= 0 || $toBarangayId <= 0 || $fromBarangayId === $toBarangayId),
            ParticipantTypeResolver::SK_OFFICIAL => match ($toType) {
                ParticipantTypeResolver::KABATAAN,
                ParticipantTypeResolver::SK_OFFICIAL,
                ParticipantTypeResolver::SK_FED => true,
                default => false,
            },
            ParticipantTypeResolver::SK_FED => in_array($toType, [
                ParticipantTypeResolver::SK_OFFICIAL,
            ], true),
            default => false,
        };
    }

    public function assertCanMessagePeer(Conversation $conversation, Authenticatable $user): void
    {
        if ($conversation->isGroup()) {
            if (! $this->isParticipant($conversation, $user)) {
                abort(403, 'You are not a member of this group.');
            }

            return;
        }

        $userType = $this->types->portalType();
        $other = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where(function ($q) use ($user, $userType) {
                $q->where('user_id', '!=', (int) $user->id)
                    ->orWhere('user_type', '!=', $userType);
            })
            ->first();

        if (! $other) {
            abort(403, 'Conversation peer not found.');
        }

        $peer = User::query()->find($other->user_id);
        if (! $peer || ! $this->canMessage($user, $peer)) {
            abort(403, 'You are not allowed to message this account.');
        }
    }

    public function assertCanCallPeer(Conversation $conversation, Authenticatable $user): void
    {
        if (! $this->isParticipant($conversation, $user)) {
            abort(403, 'You are not allowed to call this account.');
        }

        if ($conversation->isGroup()) {
            return;
        }

        $peer = $this->resolvePrivatePeerUser($conversation, $user);
        if (! $peer) {
            abort(403, 'Conversation peer not found.');
        }

        if ($this->rolesAllowCall($user, $peer) || $this->canMessage($user, $peer)) {
            return;
        }

        abort(403, 'You are not allowed to call this account.');
    }

    public function resolvePrivatePeerUser(Conversation $conversation, Authenticatable $user): ?User
    {
        $userType = $this->types->portalType();
        $callerTypes = $this->types->equivalentTypes($userType);
        if ($callerTypes === []) {
            $callerTypes = [$userType];
        }

        $other = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where(function ($query) use ($user, $callerTypes) {
                $query->where('user_id', '!=', (int) $user->id)
                    ->orWhereNotIn('user_type', $callerTypes);
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 1 ELSE 0 END', [(int) $user->id])
            ->first();

        if (! $other || ((int) $other->user_id === (int) $user->id && $this->types->typesMatch($other->user_type, $userType))) {
            return null;
        }

        return User::query()->find($other->user_id);
    }

    public function rolesAllowCall(Authenticatable $from, User $to): bool
    {
        if ((int) $from->id === (int) $to->id) {
            return false;
        }

        if (strtoupper((string) $to->status) !== User::STATUS_ACTIVE) {
            return false;
        }

        if ($this->isHiddenFederationAdministrator($to)) {
            return false;
        }

        try {
            $fromType = $this->types->fromUser($from);
            $toType = $this->types->fromUser($to);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return match ($fromType) {
            ParticipantTypeResolver::SK_OFFICIAL => in_array($toType, [
                ParticipantTypeResolver::KABATAAN,
                ParticipantTypeResolver::SK_OFFICIAL,
                ParticipantTypeResolver::SK_FED,
            ], true),
            ParticipantTypeResolver::KABATAAN => $toType === ParticipantTypeResolver::SK_OFFICIAL,
            ParticipantTypeResolver::SK_FED => $toType === ParticipantTypeResolver::SK_OFFICIAL,
            default => false,
        };
    }

    /**
     * Hide the bootstrap SK Federation Administrator from chats / search / directory.
     * Matches email, exact/partial name, and bootstrap-admin flag.
     */
    public function isHiddenFederationAdministrator(User $user): bool
    {
        if (app(BootstrapSkFedAdminService::class)->isBootstrapAdmin($user)) {
            return true;
        }

        $email = Str::lower(trim((string) ($user->email ?? '')));
        if ($email === BootstrapSkFedAdminService::bootstrapEmailNormalized()) {
            return true;
        }

        $name = mb_strtolower(trim((string) ($user->name ?? '')), 'UTF-8');
        if ($name === 'sk federation administrator') {
            return true;
        }

        // Catch renamed variants still used as the portal admin account.
        if (str_contains($name, 'federation administrator') || str_contains($name, 'sk federation admin')) {
            return true;
        }

        $role = Str::lower(trim((string) ($user->role ?? '')));

        return $role === 'admin' && str_contains($name, 'administrator');
    }

    public function isSearchableUser(User $user, ?Authenticatable $viewer = null): bool
    {
        if ($viewer) {
            return $this->canMessage($viewer, $user);
        }

        if (strtoupper((string) $user->status) !== User::STATUS_ACTIVE) {
            return false;
        }

        return $this->types->normalizeSearchRole((string) $user->role) !== null;
    }

    public function serializeUser(?User $user, ?string $type = null): ?array
    {
        if (! $user) {
            return null;
        }

        $type ??= $this->types->normalizeSearchRole((string) $user->role) ?? $this->types->fromUser($user);

        $avatar = $this->avatars->resolveForUser($user, $type, (string) $user->name);

        $online = null;
        $lastSeen = null;
        if (Schema::hasColumn('users', 'online_status')) {
            $online = $user->online_status ?? null;
        }
        if (Schema::hasColumn('users', 'last_seen')) {
            $lastSeen = optional($user->last_seen)?->toIso8601String();
        }

        if (! $user->relationLoaded('barangay') && method_exists($user, 'barangay')) {
            $user->loadMissing('barangay');
        }
        if (! $user->relationLoaded('officialProfile') && method_exists($user, 'officialProfile')) {
            $user->loadMissing('officialProfile');
        }

        $position = $user->officialProfile?->position
            ?: $user->officialProfile?->federation_position
            ?: null;

        $isFederationMember = $type === ParticipantTypeResolver::SK_FED
            || (bool) ($user->has_federation_access ?? false)
            || filled(trim((string) ($user->officialProfile?->federation_position ?? '')));

        $directoryGroup = match ($type) {
            ParticipantTypeResolver::SK_FED => 'federation',
            ParticipantTypeResolver::SK_OFFICIAL => 'officials',
            default => (string) $type,
        };

        return [
            'id' => (int) $user->id,
            'name' => $this->displayNameForUser($user),
            'role' => (string) $user->role,
            'user_type' => $type,
            'user_type_label' => $this->types->label($type),
            'directory_group' => $directoryGroup,
            'is_federation_member' => $isFederationMember,
            'profile_image_url' => $avatar,
            'online_status' => $online,
            'last_seen' => $lastSeen,
            'barangay_id' => (int) ($user->barangay_id ?? 0) ?: null,
            'barangay_name' => $user->barangay?->name,
            'position' => $position,
        ];
    }

    private function displayNameForUser(User $user): string
    {
        $profile = $user->officialProfile;
        if ($profile !== null) {
            $first = trim((string) ($profile->first_name ?? ''));
            $middle = trim((string) ($profile->middle_name ?? ''));
            $last = trim((string) ($profile->last_name ?? ''));
            $suffix = trim((string) ($profile->suffix ?? ''));
            if (in_array(mb_strtoupper($suffix, 'UTF-8'), ['', 'NONE'], true)) {
                $suffix = '';
            }

            $parts = array_values(array_filter([$first, $middle, $last, $suffix], fn (string $part): bool => $part !== ''));
            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        $fallback = trim((string) ($user->name ?? ''));

        return $fallback !== '' ? $fallback : 'User';
    }

    protected function findPrivateConversationId(int $aId, string $aType, int $bId, string $bType): ?int
    {
        $rows = DB::table('conversation_participants as cp1')
            ->join('conversation_participants as cp2', 'cp1.conversation_id', '=', 'cp2.conversation_id')
            ->join('conversations as c', 'c.id', '=', 'cp1.conversation_id')
            ->where('c.conversation_type', 'private')
            ->where('cp1.user_id', $aId)
            ->where('cp1.user_type', $aType)
            ->where('cp2.user_id', $bId)
            ->where('cp2.user_type', $bType)
            ->select('c.id')
            ->limit(1)
            ->value('id');

        return $rows ? (int) $rows : null;
    }

    protected function unreadCountForParticipant(
        int $conversationId,
        ?ConversationParticipant $mine,
        int $userId,
        string $userType
    ): int {
        $query = Message::query()
            ->where('conversation_id', $conversationId)
            ->where(function (Builder $q) use ($userId, $userType) {
                $q->where('sender_id', '!=', $userId)
                    ->orWhere('sender_type', '!=', $userType);
            });

        $lastReadMessageId = (int) ($mine?->last_read_message_id ?? 0);
        if ($lastReadMessageId > 0 && Schema::hasColumn('conversation_participants', 'last_read_message_id')) {
            $query->where('id', '>', $lastReadMessageId);
        } elseif ($mine?->last_read_at) {
            $query->where('created_at', '>', $mine->last_read_at);
        }

        return (int) $query->count();
    }
}
