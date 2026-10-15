<?php

namespace App\Modules\Communications\Services;

use App\Modules\Communications\Models\Call;
use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Models\ConversationParticipant;
use App\Modules\Communications\Models\Message;
use App\Modules\Shared\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class CallService
{
    protected ?Message $lastCallNotice = null;

    public function pullLastCallNotice(): ?Message
    {
        $notice = $this->lastCallNotice;
        $this->lastCallNotice = null;

        return $notice;
    }
    public function __construct(
        protected ParticipantTypeResolver $types,
        protected ConversationService $conversations
    ) {}

    public function start(
        Conversation $conversation,
        Authenticatable $caller,
        string $callType,
        ?int $receiverUserId = null
    ): Call {
        if (! $this->conversations->isParticipant($conversation, $caller)) {
            abort(403);
        }

        $this->conversations->assertCanCallPeer($conversation, $caller);

        $callType = strtolower(trim($callType));
        if (! in_array($callType, [Call::TYPE_VOICE, Call::TYPE_VIDEO], true)) {
            abort(422, 'Invalid call type.');
        }

        $callMax = max(2, (int) config('communications.call_max_participants', 8));
        if ($callMax < 2) {
            $callMax = 2;
        }

        $callerType = $this->types->portalType();
        $callerId = (int) $caller->id;
        $isGroupCall = false;
        $other = null;

        $otherQuery = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $callerId);

        if ($conversation->isGroup()) {
            if ($receiverUserId !== null) {
                $other = (clone $otherQuery)->where('user_id', $receiverUserId)->first();
                if (! $other) {
                    abort(403, 'Call recipient must be a member of this group.');
                }
            } else {
                $others = $otherQuery->get();
                if ($others->isEmpty()) {
                    abort(422, 'No call recipients found in this group.');
                }
                // Ring-all mesh: store caller as receiver sentinel (caller_id === receiver_id).
                $isGroupCall = true;
                $other = null;
            }
        } else {
            $other = ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where(function ($q) use ($caller, $callerType) {
                    $q->where('user_id', '!=', (int) $caller->id)
                        ->orWhere('user_type', '!=', $callerType);
                })
                ->first();
        }

        if (! $isGroupCall && ! $other) {
            abort(422, 'No call recipient found.');
        }

        $memberCount = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->count();

        if ($isGroupCall && $memberCount > $callMax) {
            // Soft cap: still allow the call; clients limit connected mesh peers.
        }

        $call = Call::query()->create([
            'conversation_id' => $conversation->id,
            'caller_id' => $callerId,
            'caller_type' => $callerType,
            'receiver_id' => $isGroupCall ? $callerId : (int) $other->user_id,
            'receiver_type' => $isGroupCall ? $callerType : $other->user_type,
            'call_type' => $callType,
            'status' => Call::STATUS_RINGING,
        ]);

        $label = $callType === Call::TYPE_VIDEO ? 'Video call' : 'Voice call';
        $prefix = $isGroupCall ? 'Group ' : '';
        $this->postCallMessage($call, $caller, $prefix.$label.' started');

        return $call;
    }

    public function updateStatus(Call $call, Authenticatable $user, string $status): Call
    {
        $this->assertCanActOnCall($call, $user);

        $status = strtolower(trim($status));
        $allowed = [
            Call::STATUS_ACCEPTED,
            Call::STATUS_REJECTED,
            Call::STATUS_MISSED,
            Call::STATUS_CANCELLED,
            Call::STATUS_ENDED,
        ];

        if (! in_array($status, $allowed, true)) {
            abort(422, 'Invalid call status.');
        }

        $previous = $call->status;
        if ($previous === $status) {
            return $call;
        }

        $terminal = [
            Call::STATUS_ENDED,
            Call::STATUS_REJECTED,
            Call::STATUS_MISSED,
            Call::STATUS_CANCELLED,
        ];
        if (in_array($previous, $terminal, true)) {
            return $call;
        }
        if ($status === Call::STATUS_MISSED && ($previous === Call::STATUS_ACCEPTED || $call->started_at !== null)) {
            return $call;
        }

        // Group mesh: first acceptor marks accepted; later reject/missed from others must not end the room.
        $isGroupCall = $this->isGroupCall($call);
        if ($isGroupCall && in_array($status, [Call::STATUS_REJECTED, Call::STATUS_MISSED], true)
            && (int) $call->caller_id !== (int) $user->id) {
            return $call;
        }

        $call->status = $status;

        if ($status === Call::STATUS_ACCEPTED && ! $call->started_at) {
            $call->started_at = now();
        }

        if (in_array($status, [Call::STATUS_ENDED, Call::STATUS_REJECTED, Call::STATUS_MISSED, Call::STATUS_CANCELLED], true)) {
            // Only caller (or terminal end) closes a group room.
            if ($isGroupCall && $status === Call::STATUS_ENDED && (int) $call->caller_id !== (int) $user->id) {
                // Non-caller leaving: do not mark the shared call ended for everyone.
                return $call;
            }

            $call->ended_at = now();
            if ($call->started_at && $status === Call::STATUS_ENDED) {
                $call->duration_seconds = max(0, $call->ended_at->diffInSeconds($call->started_at));
            }
        }

        $call->save();

        $body = $this->statusMessageBody($call, $status);
        if ($body !== null) {
            $this->postCallMessage($call, $user, $body);
        }

        return $call;
    }

    public function historyForUser(Authenticatable $user, int $limit = 50): Collection
    {
        $userId = (int) $user->id;
        $userType = $this->types->portalType();

        return Call::query()
            ->where(function ($q) use ($userId, $userType) {
                $q->where(function ($inner) use ($userId, $userType) {
                    $inner->where('caller_id', $userId)->where('caller_type', $userType);
                })->orWhere(function ($inner) use ($userId, $userType) {
                    $inner->where('receiver_id', $userId)->where('receiver_type', $userType);
                })->orWhere(function ($inner) use ($userId) {
                    // Group ring-all (caller_id === receiver_id): include conversation peers.
                    $inner->whereColumn('caller_id', 'receiver_id')
                        ->whereIn('conversation_id', function ($sub) use ($userId) {
                            $sub->select('conversation_id')
                                ->from('conversation_participants')
                                ->where('user_id', $userId);
                        });
                });
            })
            ->with(['caller', 'receiver', 'conversation'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Call $call) => $this->serialize($call, $user));
    }

    public function serialize(Call $call, Authenticatable $viewer): array
    {
        $viewerType = $this->types->portalType();
        $isGroupCall = $this->isGroupCall($call);
        $isCaller = (int) $call->caller_id === (int) $viewer->id && $call->caller_type === $viewerType;
        $peer = $isCaller ? $call->receiver : $call->caller;
        $peerType = $isCaller ? $call->receiver_type : $call->caller_type;

        $conversation = $call->relationLoaded('conversation')
            ? $call->conversation
            : $call->conversation()->first();

        $peerPayload = $isGroupCall
            ? [
                'id' => 0,
                'name' => $conversation?->name ?: 'Group call',
                'is_group' => true,
                'profile_image_url' => $conversation?->avatar_url,
            ]
            : $this->conversations->serializeUser($peer instanceof User ? $peer : null, $peerType);

        $invitees = [];
        if ($isGroupCall && $conversation) {
            $invitees = ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', (int) $viewer->id)
                ->with('user')
                ->get()
                ->map(function (ConversationParticipant $row) {
                    return [
                        'id' => (int) $row->user_id,
                        'user_type' => $row->user_type,
                        'name' => $row->user?->name ?? 'Member',
                    ];
                })
                ->values()
                ->all();
        }

        return [
            'id' => $call->id,
            'conversation_id' => $call->conversation_id,
            'call_type' => $call->call_type,
            'status' => $call->status,
            'caller_id' => (int) $call->caller_id,
            'caller_type' => $call->caller_type,
            'receiver_id' => (int) $call->receiver_id,
            'receiver_type' => $call->receiver_type,
            'is_group_call' => $isGroupCall,
            'call_max_participants' => (int) config('communications.call_max_participants', 8),
            'invitees' => $invitees,
            'started_at' => optional($call->started_at)?->toIso8601String(),
            'ended_at' => optional($call->ended_at)?->toIso8601String(),
            'duration_seconds' => $call->duration_seconds,
            'direction' => $isCaller ? 'outgoing' : 'incoming',
            'peer' => $peerPayload,
            'created_at' => optional($call->created_at)?->toIso8601String(),
        ];
    }

    public function isGroupCall(Call $call): bool
    {
        return (int) $call->caller_id === (int) $call->receiver_id
            && (string) $call->caller_type === (string) $call->receiver_type;
    }

    protected function statusMessageBody(Call $call, string $status): ?string
    {
        $isGroup = $this->isGroupCall($call);
        $label = ($isGroup ? 'Group ' : '').($call->call_type === Call::TYPE_VIDEO ? 'Video call' : 'Voice call');

        return match ($status) {
            Call::STATUS_ACCEPTED => $label.' answered',
            Call::STATUS_ENDED => $label.' ended'
                .($call->duration_seconds ? ' · '.$this->formatDuration((int) $call->duration_seconds) : ''),
            Call::STATUS_MISSED => 'Call ended. No pickup.',
            Call::STATUS_REJECTED => $label.' declined',
            Call::STATUS_CANCELLED => $label.' cancelled',
            default => null,
        };
    }

    protected function formatDuration(int $seconds): string
    {
        $m = intdiv(max(0, $seconds), 60);
        $s = max(0, $seconds) % 60;

        return sprintf('%d:%02d', $m, $s);
    }

    protected function postCallMessage(Call $call, Authenticatable $actor, string $body): Message
    {
        $message = Message::query()->create([
            'conversation_id' => $call->conversation_id,
            'sender_id' => (int) $actor->id,
            'sender_type' => $this->types->portalType(),
            'body' => $body,
            'message_type' => 'call',
        ]);
        $this->lastCallNotice = $message;

        Conversation::query()->whereKey($call->conversation_id)->update([
            'updated_at' => now(),
        ]);

        return $message;
    }

    protected function assertCanActOnCall(Call $call, Authenticatable $user): void
    {
        $userId = (int) $user->id;
        $userType = $this->types->portalType();

        $isCaller = (int) $call->caller_id === $userId && $call->caller_type === $userType;
        $isReceiver = (int) $call->receiver_id === $userId && $call->receiver_type === $userType;

        if ($isCaller || $isReceiver) {
            return;
        }

        if ($this->isGroupCall($call)) {
            $inRoom = ConversationParticipant::query()
                ->where('conversation_id', $call->conversation_id)
                ->where('user_id', $userId)
                ->exists();
            if ($inRoom) {
                return;
            }
        }

        abort(403);
    }
}
