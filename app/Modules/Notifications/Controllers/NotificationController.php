<?php

namespace App\Modules\Notifications\Controllers;

use App\Modules\Shared\Controllers\Controller;
use App\Services\SkFederationsNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private readonly SkFederationsNotificationService $notificationService,
    ) {}

    public function index(): View
    {
        $user = Auth::user();
        $notifications = $this->notificationService->allForUser($user);
        $unreadCount = $this->notificationService->unreadCountForUser($user);

        return view('notifications::index', compact('notifications', 'unreadCount'));
    }

    public function list(Request $request): JsonResponse
    {
        $user = Auth::user();
        $limit = max(1, min(20, (int) $request->integer('limit', 5)));

        return response()->json([
            'data' => $this->notificationService->recentForUser($user, $limit),
            'unread_count' => $this->notificationService->unreadCountForUser($user),
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $this->notificationService->markRead($user, $id);

        return response()->json([
            'success' => true,
            'unread_count' => $this->notificationService->unreadCountForUser($user),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $this->notificationService->markAllRead($user);

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }
}
