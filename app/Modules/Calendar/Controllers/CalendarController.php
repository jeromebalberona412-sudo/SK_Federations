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
        $year = (int) now()->year;
        $month = (int) now()->month;
        $notes = $this->cachedNotesForMonth($request, $year, $month);

        return view('Calendar::calendar', [
            'user' => $request->user(),
            'calendarBoot' => [
                'year' => $year,
                'month' => $month,
                'notes' => $notes,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $notes = $this->cachedNotesForMonth($request, $year, $month);

        return response()->json(['data' => $notes])->header('Cache-Control', 'private, max-age=60');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'note_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string'],
        ]);

        $validated['title'] = $this->assertValidNoteTitle($validated['title']);
        $validated['content'] = $this->assertValidNoteContent($validated['content']);

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
            'content' => ['required', 'string'],
        ]);

        $validated['title'] = $this->assertValidNoteTitle($validated['title']);
        $validated['content'] = $this->assertValidNoteContent($validated['content']);

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
        $today = now()->startOfDay();

        if ($noteDate->lt($today)) {
            throw ValidationException::withMessages([
                'note_date' => ['No past dates to add note.'],
            ]);
        }

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

        // Existing notes (including past dates) can be viewed/edited/deleted.
        // Only block next year and beyond.
        if ($noteYear > $currentYear) {
            throw ValidationException::withMessages([
                'note_date' => ['Notes cannot be edited or deleted for next year or beyond.'],
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cachedNotesForMonth(Request $request, int $year, int $month): array
    {
        return $this->pageCache->remember('calendar.notes', function () use ($year, $month) {
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));

            return SkFederationCalendarNote::query()
                ->whereBetween('note_date', [$start, $end])
                ->orderBy('note_date')
                ->get()
                ->map(fn (SkFederationCalendarNote $note) => $this->formatNote($note))
                ->values()
                ->all();
        }, [
            'tenant_id' => $request->user()?->tenant_id,
            'year' => $year,
            'month' => $month,
        ]);
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

    /**
     * Title: required, max 50, no spaces or newlines.
     */
    protected function assertValidNoteTitle(string $title): string
    {
        $clean = preg_replace('/\s+/u', '', $title) ?? '';
        $clean = mb_substr($clean, 0, 50);

        if ($clean === '') {
            throw ValidationException::withMessages([
                'title' => ['Title is required.'],
            ]);
        }

        if (mb_strlen($clean) > 50) {
            throw ValidationException::withMessages([
                'title' => ['Title must be 50 characters or less.'],
            ]);
        }

        return $clean;
    }

    /**
     * Same visual-line weighted rule as Community Feed / Communications chat:
     * a completed newline counts as at least one full line width, not +1.
     */
    protected function assertValidNoteContent(string $content): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);

        if ($normalized === '' || preg_match('/\S/u', $normalized) !== 1) {
            throw ValidationException::withMessages([
                'content' => ['Content is required.'],
            ]);
        }

        if ($this->countedNoteLength($normalized) > 1000) {
            throw ValidationException::withMessages([
                'content' => ['Content must be 1,000 characters or less (new lines count as a full line).'],
            ]);
        }

        return $normalized;
    }

    protected function countedNoteLength(string $normalizedBody, int $charsPerLine = 36): int
    {
        $cpl = max(8, $charsPerLine);
        $lines = explode("\n", $normalizedBody);
        $total = 0;
        $lastIndex = count($lines) - 1;

        foreach ($lines as $index => $line) {
            $len = mb_strlen($line);
            if ($index < $lastIndex) {
                $total += max($cpl, $len);
            } else {
                $total += $len;
            }
        }

        return $total;
    }
}
