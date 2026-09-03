<?php

namespace App\Modules\Communications\Controllers;

use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversations
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->conversations->listForUser(
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
}
