<?php

namespace App\Modules\Communications\Policies;

use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Services\ConversationService;
use Illuminate\Contracts\Auth\Authenticatable;

class ConversationPolicy
{
    public function __construct(
        protected ConversationService $conversations
    ) {}

    public function view(Authenticatable $user, Conversation $conversation): bool
    {
        return $this->conversations->isParticipant($conversation, $user);
    }

    public function send(Authenticatable $user, Conversation $conversation): bool
    {
        return $this->conversations->isParticipant($conversation, $user);
    }

    public function call(Authenticatable $user, Conversation $conversation): bool
    {
        return $this->conversations->isParticipant($conversation, $user);
    }
}
