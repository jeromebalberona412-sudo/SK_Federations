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
    public function __construct(
        protected ParticipantTypeResolver $types,
        protected ConversationService $conversations
    ) {}

    public function start(Conversation $conversation, Authenticatable $caller, string $callType): Call
    {
        if (! $this->conversations->isParticipant($conversation, $caller)) {
            abort(403);
        }

        $this->conversations->assertCanMessagePeer($conversation, $caller);

        $callType = strtolower(trim($callType));
        if (! in_array($callType, [Call::TYPE_VOICE, Call::TYPE_VIDEO], true)) {
            abort(422, 'Invalid call type.');
        }

        $callerType = $this->types->portalType();
        $other = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where(function ($q) use ($caller, $callerType) {
                $q->where('user_id', '!=', (int) $caller->id)
                    ->orWhere('user_type', '!=', $callerType);
            })
            ->first();

        if (! $other) {
            abort(422, 'No call recipient found.');
        }

        $call = Call::query()->create([
            'conversation_id' => $conversation->id,
            'caller_id' => (int) $caller->id,
            'caller_type' => $callerType,
            'receiver_id' => (int) $other->user_id,
            'receiver_type' => $other->user_type,
            'call_type' => $callType,
            'status' => Call::STATUS_RINGING,
        ]);

        $label = $callType === Call::TYPE_VIDEO ? 'Video call' : 'Voice call';
        $this->postCallMessage($call, $caller, $label.' started');

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

        // Avoid duplicate chat lines if the same terminal status is posted twice.
        $previous = $call->status;
        if ($previous === $status) {
            return $call;
        }

        $call->status = $status;

        if ($status === Call::STATUS_ACCEPTED && ! $call->started_at) {
            $call->started_at = now();
        }

        if (in_array($status, [Call::STATUS_ENDED, Call::STATUS_REJECTED, Call::STATUS_MISSED, Call::STATUS_CANCELLED], true)) {
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
                });
            })
            ->with(['caller', 'receiver'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Call $call) => $this->serialize($call, $user));
    }

    public function serialize(Call $call, Authenticatable $viewer): array
    {
        $viewerType = $this->types->portalType();
        $isCaller = (int) $call->caller_id === (int) $viewer->id && $call->caller_type === $viewerType;
        $peer = $isCaller ? $call->receiver : $call->caller;
        $peerType = $isCaller ? $call->receiver_type : $call->caller_type;

        return [
            'id' => $call->id,
            'conversation_id' => $call->conversation_id,
            'call_type' => $call->call_type,
            'status' => $call->status,
            'caller_id' => (int) $call->caller_id,
            'caller_type' => $call->caller_type,
            'receiver_id' => (int) $call->receiver_id,
            'receiver_type' => $call->receiver_type,
            'started_at' => optional($call->started_at)?->toIso8601String(),
            'ended_at' => optional($call->ended_at)?->toIso8601String(),
            'duration_seconds' => $call->duration_seconds,
            'direction' => $isCaller ? 'outgoing' : 'incoming',
            'peer' => $this->conversations->serializeUser($peer instanceof User ? $peer : null, $peerType),
            'created_at' => optional($call->created_at)?->toIso8601String(),
        ];
    }

    protected function statusMessageBody(Call $call, string $status): ?string
    {
        $label = $call->call_type === Call::TYPE_VIDEO ? 'Video call' : 'Voice call';

        return match ($status) {
            Call::STATUS_ACCEPTED => $label.' answered',
            Call::STATUS_ENDED => $label.' ended'
                .($call->duration_seconds ? ' · '.$this->formatDuration((int) $call->duration_seconds) : ''),
            Call::STATUS_MISSED => 'Missed '.$label,
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

    protected function postCallMessage(Call $call, Authenticatable $actor, string $body): void
    {
        Message::query()->create([
            'conversation_id' => $call->conversation_id,
            'sender_id' => (int) $actor->id,
            'sender_type' => $this->types->portalType(),
            'body' => $body,
            'message_type' => 'call',
        ]);

        Conversation::query()->whereKey($call->conversation_id)->update([
            'updated_at' => now(),
        ]);
    }

    protected function assertCanActOnCall(Call $call, Authenticatable $user): void
    {
        $userId = (int) $user->id;
        $userType = $this->types->portalType();

        $isCaller = (int) $call->caller_id === $userId && $call->caller_type === $userType;
        $isReceiver = (int) $call->receiver_id === $userId && $call->receiver_type === $userType;

        if (! $isCaller && ! $isReceiver) {
            abort(403);
        }
    }
}
