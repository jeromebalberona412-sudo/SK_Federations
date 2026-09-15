<?php

namespace App\Modules\Communications\Policies;

use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Services\ConversationService;
use App\Modules\Communications\Services\DefaultGroupChatService;
use App\Modules\Shared\Models\User;

class ConversationPolicy
{
    public function __construct(
        protected ConversationService $conversations,
        protected DefaultGroupChatService $defaultGroups,
    ) {}

    public function rename(User $user, Conversation $conversation): bool
    {
        return $this->defaultGroups->canRename($conversation, $user);
    }

    public function updateAvatar(User $user, Conversation $conversation): bool
    {
        return $this->defaultGroups->canChangeAvatar($conversation, $user);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->conversations->isParticipant($conversation, $user);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->conversations->isParticipant($conversation, $user);
    }

    public function call(User $user, Conversation $conversation): bool
    {
        return $this->conversations->isParticipant($conversation, $user);
    }
}
