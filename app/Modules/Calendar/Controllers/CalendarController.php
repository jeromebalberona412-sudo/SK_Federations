<?php

namespace App\Modules\Calendar\Controllers;

use App\Models\SkFederationCalendarNote;
use App\Modules\Shared\Controllers\Controller;
use App\Services\SkFedActivityService;
use App\Services\SkFedPageCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __construct(
        private readonly SkFedActivityService $activityService,
        private readonly SkFedPageCache $pageCache,
    ) {}

    public function page(Request $request): View
    {
        return view('Calendar::calendar', ['user' => $request->user()]);
    }

    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $notes = $this->pageCache->remember('calendar.notes', function () use ($year, $month) {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));

            return SkFederationCalendarNote::query()
                ->whereBetween('note_date', [$start, $end])
                ->orderBy('note_date')
                ->get()
                ->map(fn (SkFederationCalendarNote $note) => $this->formatNote($note))
                ->values()
                ->all();
        }, ['year' => $year, 'month' => $month]);

        return response()->json(['data' => $notes])->header('Cache-Control', 'private, max-age=60');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'note_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string', 'max:500'],
        ]);

        $this->assertCanCreateNote($validated['note_date']);

        $exists = SkFederationCalendarNote::query()
            ->whereDate('note_date', $validated['note_date'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'note_date' => ['A note already exists for this date.'],
            ]);
        }

        $note = SkFederationCalendarNote::create([
            'user_id' => $user->id,
            'note_date' => $validated['note_date'],
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        $this->activityService->log(
            $user,
            'calendar.create',
            'Added calendar note: '.$validated['title'],
            ['note_id' => $note->id, 'note_date' => $validated['note_date']]
        );

        $this->pageCache->bump('calendar.notes');

        return response()->json([
            'message' => 'Note saved.',
            'data' => $this->formatNote($note),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $note = $this->findNote($id);

        $this->assertCanModifyExistingNote($note->note_date->toDateString());

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string', 'max:500'],
        ]);

        $note->update($validated);

        $this->activityService->log(
            $user,
            'calendar.update',
            'Updated calendar note: '.$validated['title'],
            ['note_id' => $note->id]
        );

        $this->pageCache->bump('calendar.notes');

        return response()->json([
            'message' => 'Note updated.',
            'data' => $this->formatNote($note->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $note = $this->findNote($id);

        $this->assertCanModifyExistingNote($note->note_date->toDateString());

        $title = $note->title;
        $note->delete();

        $this->activityService->log(
            $user,
            'calendar.delete',
            'Deleted calendar note: '.$title,
            ['note_id' => $id]
        );

        $this->pageCache->bump('calendar.notes');

        return response()->json(['message' => 'Note deleted.']);
    }

    protected function findNote(int $id): SkFederationCalendarNote
    {
        $note = SkFederationCalendarNote::query()->find($id);

        if ($note === null) {
            throw ValidationException::withMessages([
                'note' => ['Calendar note not found.'],
            ]);
        }

        return $note;
    }

    protected function assertCanCreateNote(string $date): void
    {
        $noteDate = Carbon::parse($date)->startOfDay();
        $currentYear = (int) now()->year;
        $noteYear = (int) $noteDate->year;

        if ($noteYear < $currentYear) {
            throw ValidationException::withMessages([
                'note_date' => ['Notes cannot be added for past years.'],
            ]);
        }

        if ($noteYear > $currentYear) {
            throw ValidationException::withMessages([
                'note_date' => ['Notes cannot be added for next year or beyond.'],
            ]);
        }
    }

    protected function assertCanModifyExistingNote(string $date): void
    {
        $noteDate = Carbon::parse($date)->startOfDay();
        $currentYear = (int) now()->year;
        $noteYear = (int) $noteDate->year;

        if ($noteYear > $currentYear) {
            throw ValidationException::withMessages([
                'note_date' => ['Notes cannot be edited or deleted for next year or beyond.'],
            ]);
        }
    }

    protected function formatNote(SkFederationCalendarNote $note): array
    {
        return [
            'id' => $note->id,
            'note_date' => $note->note_date->format('Y-m-d'),
            'title' => $note->title,
            'content' => $note->content,
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }
}
