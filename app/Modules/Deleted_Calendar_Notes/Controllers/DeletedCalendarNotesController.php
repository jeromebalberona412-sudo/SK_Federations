<?php

namespace App\Modules\Deleted_Calendar_Notes\Controllers;

use App\Models\SkFederationCalendarNote;
use App\Modules\Shared\Controllers\Controller;
use App\Services\SkFedActivityService;
use App\Services\SkFedPageCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DeletedCalendarNotesController extends Controller
{
    public const RETENTION_DAYS = 30;

    public function __construct(
        private readonly SkFedActivityService $activityService,
        private readonly SkFedPageCache $pageCache,
    ) {}

    public function index(): View
    {
        $this->purgeExpired();

        return view('Deleted_Calendar_Notes::deleted-calendar-notes');
    }

    public function data(Request $request): JsonResponse
    {
        $this->purgeExpired();

        $query = SkFederationCalendarNote::onlyTrashed()
            ->orderByDesc('deleted_at');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('content', 'ilike', "%{$search}%");
            });
        }

        $filter = $request->get('filter', 'all');
        if ($filter === 'today') {
            $query->whereDate('deleted_at', now()->toDateString());
        } elseif ($filter === 'week') {
            $query->where('deleted_at', '>=', now()->startOfWeek());
        } elseif ($filter === 'month') {
            $query->where('deleted_at', '>=', now()->startOfMonth());
        }

        $records = $query->get();

        $data = $records->map(function (SkFederationCalendarNote $note) {
            $deletedAt = $note->deleted_at;
            $daysRemaining = $this->daysRemaining($deletedAt);

            return [
                'id' => $note->id,
                'title' => $note->title,
                'content' => $note->content,
                'note_date' => $note->note_date?->format('Y-m-d'),
                'note_date_label' => $note->note_date?->format('M d, Y') ?? '—',
                'deleted_date' => $deletedAt?->format('M d, Y') ?? '—',
                'deleted_time' => $deletedAt?->format('h:i A') ?? '—',
                'deleted_at' => $deletedAt?->toIso8601String(),
                'days_remaining' => $daysRemaining,
                'auto_delete_label' => $daysRemaining <= 0
                    ? 'Deleting soon'
                    : 'Auto delete in '.$daysRemaining.' day'.($daysRemaining === 1 ? '' : 's'),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function restore(int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $note = SkFederationCalendarNote::onlyTrashed()->findOrFail($id);

        $conflict = SkFederationCalendarNote::query()
            ->whereDate('note_date', $note->note_date)
            ->exists();

        if ($conflict) {
            $dateLabel = $note->note_date?->format('F j') ?? 'this date';

            return response()->json([
                'success' => false,
                'code' => 'restore_conflict',
                'title' => 'Cannot restore calendar note',
                'message' => 'A calendar note already exists for '.$dateLabel.'. Please delete or move the existing note before restoring this archived note.',
                'note_date_label' => $dateLabel,
            ], 422);
        }

        $note->restore();

        $this->activityService->log(
            $user,
            'calendar.restore',
            'Restored calendar note: '.$note->title,
            [
                'note_id' => $note->id,
                'note_date' => $note->note_date->toDateString(),
            ]
        );

        $this->pageCache->bump('calendar.notes');

        return response()->json([
            'success' => true,
            'message' => 'Note restored to Calendar.',
            'title' => $note->title,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $note = SkFederationCalendarNote::onlyTrashed()->findOrFail($id);

        $title = $note->title;
        $noteDate = $note->note_date?->toDateString();

        $note->forceDelete();

        $this->activityService->log(
            $user,
            'calendar.force_delete',
            'Permanently deleted calendar note: '.$title,
            [
                'note_id' => $id,
                'note_date' => $noteDate,
            ]
        );

        $this->pageCache->bump('calendar.notes');

        return response()->json([
            'success' => true,
            'message' => 'Calendar note permanently deleted.',
        ]);
    }

    public function purgeExpired(): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        return SkFederationCalendarNote::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->forceDelete();
    }

    private function daysRemaining(?Carbon $deletedAt): int
    {
        if ($deletedAt === null) {
            return self::RETENTION_DAYS;
        }

        $expiresAt = $deletedAt->copy()->addDays(self::RETENTION_DAYS);

        if ($expiresAt->lte(now())) {
            return 0;
        }

        $secondsLeft = $expiresAt->getTimestamp() - now()->getTimestamp();

        return (int) max(1, (int) ceil($secondsLeft / 86400));
    }
}
