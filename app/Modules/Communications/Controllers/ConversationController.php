<?php

namespace App\Modules\Communications\Controllers;

use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Services\ConversationService;
use App\Modules\Communications\Services\DefaultGroupChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversations,
        protected DefaultGroupChatService $defaultGroups,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $includeDirectory = $request->boolean('include_directory');
        $items = $includeDirectory
            ? $this->conversations->headerInboxForUser(Auth::user())
            : $this->conversations->listForUser(
                Auth::user(),
                $request->string('q')->toString() ?: null
            );

        return response()->json(['conversations' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $conversation = $this->conversations->findOrCreatePrivate(
            Auth::user(),
            (int) $validated['user_id']
        );

        return response()->json([
            'conversation' => $this->conversations->showPayload($conversation, Auth::user()),
        ], 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        return response()->json([
            'conversation' => $this->conversations->showPayload($conversation, Auth::user()),
        ]);
    }

    public function markRead(Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $this->conversations->markRead($conversation, Auth::user());

        return response()->json(['ok' => true]);
    }

    public function rename(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('rename', $conversation);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $conversation = $this->defaultGroups->rename(
            $conversation,
            Auth::user(),
            (string) $validated['name']
        );

        return response()->json([
            'conversation' => $this->conversations->showPayload($conversation, Auth::user()),
        ]);
    }

    public function updateAvatar(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('updateAvatar', $conversation);

        $validated = $request->validate([
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp,gif',
                'max:10240',
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
        ]);

        $conversation = $this->defaultGroups->updateAvatar(
            $conversation,
            Auth::user(),
            $validated['avatar']
        );

        return response()->json([
            'conversation' => $this->conversations->showPayload($conversation, Auth::user()),
        ]);
    }

    public function members(Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        if (! $conversation->isGroup()) {
            abort(422, 'Members are only available for group conversations.');
        }

        $conversation->load(['participants.user']);

        return response()->json([
            'conversation' => $this->conversations->showPayload($conversation, Auth::user()),
            'members' => $this->defaultGroups->serializeMembers($conversation, $this->conversations),
            'member_count' => $conversation->participants->count(),
            'max_members' => $conversation->max_members,
        ]);
    }
}
