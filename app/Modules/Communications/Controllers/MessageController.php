<?php

namespace App\Modules\Communications\Controllers;

use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Models\Message;
use App\Modules\Communications\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct(
        protected MessageService $messages
    ) {}

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $beforeId = $request->integer('before_id') ?: null;
        $items = $this->messages->paginate($conversation, Auth::user(), $beforeId ?: null);

        return response()->json([
            'messages' => $items,
            'reaction_emojis' => $this->messages->allowedReactions(),
        ]);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('send', $conversation);

        $max = (int) config('communications.message_max_length', 5000);
        $hasFiles = $request->hasFile('attachments');

        $validated = $request->validate([
            'body' => [$hasFiles ? 'nullable' : 'required', 'string', 'max:'.$max],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file'],
        ]);

        $body = (string) ($validated['body'] ?? '');
        $files = $hasFiles ? (array) $request->file('attachments', []) : [];

        $message = $files !== []
            ? $this->messages->sendWithAttachments($conversation, Auth::user(), $body, $files)
            : $this->messages->send($conversation, Auth::user(), $body);

        return response()->json([
            'message' => $this->messages->serialize($message, Auth::user()),
        ], 201);
    }

    public function toggleReaction(Request $request, Message $message): JsonResponse
    {
        $conversation = $message->conversation;
        abort_if($conversation === null, 404);
        Gate::authorize('view', $conversation);

        abort_if($message->deleted_for_all_at !== null, 422, 'Cannot react to a deleted message.');

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        $payload = $this->messages->toggleReaction($message, Auth::user(), $validated['emoji']);

        return response()->json($payload);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $conversation = $message->conversation;
        abort_if($conversation === null, 404);
        Gate::authorize('send', $conversation);

        $max = (int) config('communications.message_max_length', 5000);
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.$max],
        ]);

        $updated = $this->messages->edit($message, Auth::user(), $validated['body']);

        return response()->json([
            'message' => $this->messages->serialize($updated, Auth::user()),
        ]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $conversation = $message->conversation;
        abort_if($conversation === null, 404);
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'scope' => ['required', 'in:me,all'],
        ]);

        if ($validated['scope'] === 'all') {
            $updated = $this->messages->deleteForAll($message, Auth::user());

            return response()->json([
                'scope' => 'all',
                'message' => $this->messages->serialize($updated, Auth::user()),
            ]);
        }

        $this->messages->deleteForMe($message, Auth::user());

        return response()->json([
            'scope' => 'me',
            'message_id' => (int) $message->id,
        ]);
    }
}
