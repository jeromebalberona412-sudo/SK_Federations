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

        $max = (int) config('communications.message_max_length', 1000);
        $hasFiles = $request->hasFile('attachments');

        // Normalize line endings before length checks so \r\n counts as one character.
        $body = str_replace(["\r\n", "\r"], "\n", (string) $request->input('body', ''));
        $request->merge(['body' => $body]);

        $request->validate([
            // Use present (not required) so spaces/newlines are allowed in body text.
            // Max length is enforced after normalize (below), not via Laravel max.
            'body' => [$hasFiles ? 'nullable' : 'present', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file'],
        ]);

        $files = $hasFiles ? array_values(array_filter((array) $request->file('attachments', []))) : [];

        if ($files === [] && trim($body) === '') {
            return response()->json(['message' => 'Message body is required.'], 422);
        }

        if (mb_strlen($body) > $max) {
            return response()->json([
                'message' => 'Message cannot exceed 1000 characters.',
            ], 422);
        }

        $lineCount = $body === '' ? 0 : count(preg_split("/\r\n|\n|\r/", $body) ?: []);
        $maxLines = (int) config('communications.message_max_lines', 500);
        if ($lineCount > $maxLines) {
            return response()->json(['message' => 'Message cannot exceed '.$maxLines.' lines.'], 422);
        }

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

        $max = (int) config('communications.message_max_length', 1000);
        $body = str_replace(["\r\n", "\r"], "\n", (string) $request->input('body', ''));

        if (trim($body) === '') {
            return response()->json(['message' => 'Message body is required.'], 422);
        }

        if (mb_strlen($body) > $max) {
            return response()->json([
                'message' => 'Message cannot exceed 1000 characters.',
            ], 422);
        }

        $lineCount = count(preg_split("/\r\n|\n|\r/", $body) ?: []);
        $maxLines = (int) config('communications.message_max_lines', 500);
        if ($lineCount > $maxLines) {
            return response()->json(['message' => 'Message cannot exceed '.$maxLines.' lines.'], 422);
        }

        $updated = $this->messages->edit($message, Auth::user(), $body);

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
