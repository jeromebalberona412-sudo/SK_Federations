<?php

namespace App\Modules\BarangayMonitoring\Services;

use App\Models\AbyipSubmissionSchedule;
use App\Models\AbyipSubmissionScheduleHistory;
use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AbyipSubmissionScheduleService
{
    public function __construct(private readonly AuditLogInterface $auditLog) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listSchedules(): Collection
    {
        if (! Schema::hasTable('abyip_submission_schedules')) {
            return collect();
        }

        return AbyipSubmissionSchedule::query()
            ->with($this->scheduleRelations(includeHistory: true))
            ->orderByDesc('fiscal_year')
            ->get()
            ->map(fn (AbyipSubmissionSchedule $schedule) => $this->formatSchedule($schedule, includeHistory: true));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentSchedule(?int $fiscalYear = null): ?array
    {
        if (! Schema::hasTable('abyip_submission_schedules')) {
            return null;
        }

        $year = $fiscalYear ?? (int) date('Y');

        $schedule = AbyipSubmissionSchedule::query()
            ->with($this->scheduleRelations(includeHistory: true))
            ->where('fiscal_year', $year)
            ->where('status', '!=', AbyipSubmissionSchedule::STATUS_CANCELLED)
            ->first();

        if ($schedule === null) {
            $schedule = AbyipSubmissionSchedule::query()
                ->with($this->scheduleRelations(includeHistory: true))
                ->where('status', '!=', AbyipSubmissionSchedule::STATUS_CANCELLED)
                ->orderByDesc('fiscal_year')
                ->first();
        }

        return $schedule ? $this->formatSchedule($schedule, includeHistory: true) : null;
    }

    public function canCreateForCurrentYear(): bool
    {
        if (! Schema::hasTable('abyip_submission_schedules')) {
            return true;
        }

        $year = (int) date('Y');

        return ! AbyipSubmissionSchedule::query()->where('fiscal_year', $year)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(User $user, array $data): array
    {
        $this->assertTableExists();

        $validated = $this->validateSchedulePayload($data);
        $this->assertNoDuplicateYear($validated['fiscal_year']);

        return DB::transaction(function () use ($user, $validated) {
            $schedule = AbyipSubmissionSchedule::query()->create([
                'tenant_id' => $user->tenant_id,
                'fiscal_year' => $validated['fiscal_year'],
                'title' => $validated['title'],
                'date_start' => $validated['date_start'],
                'deadline' => $validated['deadline'],
                'original_deadline' => $validated['deadline'],
                'status' => $this->resolveStatus($validated['date_start'], $validated['deadline']),
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]);

            $allowLateExtension = filter_var(
                $validated['allow_late_extension'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

            if ($allowLateExtension && DB::getDriverName() === 'pgsql') {
                DB::table('abyip_submission_schedules')
                    ->where('id', $schedule->id)
                    ->update(['allow_late_extension' => DB::raw('TRUE')]);
                $schedule->refresh();
            } elseif ($allowLateExtension) {
                $schedule->allow_late_extension = true;
                $schedule->save();
            }

            $this->recordHistory($schedule, AbyipSubmissionScheduleHistory::ACTION_CREATED, null, $schedule->deadline, null, $schedule->date_start, 'Initial schedule created.', $user);
            $this->logScheduleEvent($user, 'abyip_schedule.created', $schedule, [
                'action' => 'created',
                'new_deadline' => $schedule->deadline?->format('M j, Y'),
                'new_date_start' => $schedule->date_start?->format('M j, Y'),
            ]);

            return $this->formatSchedule($schedule->fresh($this->scheduleRelations(includeHistory: true)));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(User $user, int|string $id, array $data): array
    {
        $schedule = $this->findSchedule($id);
        $this->assertEditable($schedule);

        $validated = $this->validateSchedulePayload($data, $schedule->id);
        $oldDeadline = $schedule->deadline;
        $oldStart = $schedule->date_start;

        return DB::transaction(function () use ($user, $schedule, $validated, $oldDeadline, $oldStart) {
            $schedule->update([
                'fiscal_year' => $validated['fiscal_year'],
                'title' => $validated['title'],
                'date_start' => $validated['date_start'],
                'deadline' => $validated['deadline'],
                'status' => $this->resolveStatus($validated['date_start'], $validated['deadline'], $schedule),
                'updated_by_user_id' => $user->id,
            ]);

            $this->recordHistory(
                $schedule,
                AbyipSubmissionScheduleHistory::ACTION_UPDATED,
                $oldDeadline,
                $schedule->deadline,
                $oldStart,
                $schedule->date_start,
                'Schedule updated.',
                $user
            );

            $this->logScheduleEvent($user, 'abyip_schedule.updated', $schedule, [
                'action' => 'updated',
                'old_deadline' => $oldDeadline?->format('M j, Y'),
                'new_deadline' => $schedule->deadline?->format('M j, Y'),
            ]);

            return $this->formatSchedule($schedule->fresh($this->scheduleRelations(includeHistory: true)), includeHistory: true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function extendDeadline(User $user, int|string $id, string $newDeadline, ?string $reason = null): array
    {
        $schedule = $this->findSchedule($id);
        $this->assertExtendable($schedule);

        $tz = config('app.timezone', 'Asia/Manila');
        $newDeadlineDate = Carbon::parse($newDeadline, $tz)->startOfDay();
        $oldDeadline = $schedule->deadline;

        if ($newDeadlineDate->lte($oldDeadline)) {
            throw ValidationException::withMessages([
                'new_deadline' => ['The new deadline must be after the current deadline.'],
            ]);
        }

        return DB::transaction(function () use ($user, $schedule, $newDeadlineDate, $oldDeadline, $reason) {
            $schedule->update([
                'deadline' => $newDeadlineDate,
                'status' => AbyipSubmissionSchedule::STATUS_EXTENDED,
                'updated_by_user_id' => $user->id,
            ]);

            $this->recordHistory(
                $schedule,
                AbyipSubmissionScheduleHistory::ACTION_EXTENDED,
                $oldDeadline,
                $schedule->deadline,
                null,
                null,
                $reason ?: 'Deadline extended.',
                $user
            );

            $this->logScheduleEvent($user, 'abyip_schedule.extended', $schedule, [
                'action' => 'extended',
                'old_deadline' => $oldDeadline?->format('M j, Y'),
                'new_deadline' => $schedule->deadline?->format('M j, Y'),
                'reason' => $reason,
            ]);

            return $this->formatSchedule($schedule->fresh($this->scheduleRelations(includeHistory: true)), includeHistory: true);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(User $user, int|string $id, ?string $reason = null): array
    {
        $schedule = $this->findSchedule($id);

        if ($schedule->status === AbyipSubmissionSchedule::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'schedule' => ['This schedule is already cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($user, $schedule, $reason) {
            $schedule->update([
                'status' => AbyipSubmissionSchedule::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by_user_id' => $user->id,
            ]);

            $this->recordHistory(
                $schedule,
                AbyipSubmissionScheduleHistory::ACTION_CANCELLED,
                $schedule->deadline,
                null,
                null,
                null,
                $reason ?: 'Schedule cancelled.',
                $user
            );

            $this->logScheduleEvent($user, 'abyip_schedule.cancelled', $schedule, [
                'action' => 'cancelled',
                'reason' => $reason,
            ]);

            return $this->formatSchedule($schedule->fresh($this->scheduleRelations(includeHistory: true)), includeHistory: true);
        });
    }

    public function destroy(User $user, int|string $id): void
    {
        $schedule = $this->findSchedule($id);

        DB::transaction(function () use ($schedule) {
            if (Schema::hasTable('abyip_submission_schedule_histories')) {
                $schedule->histories()->delete();
            }

            $schedule->delete();
        });

        $this->logScheduleEvent($user, 'abyip_schedule.deleted', $schedule, [
            'action' => 'deleted',
            'fiscal_year' => $schedule->fiscal_year,
        ]);
    }

    private function findSchedule(int|string $id): AbyipSubmissionSchedule
    {
        $this->assertTableExists();

        return AbyipSubmissionSchedule::query()->findOrFail($id);
    }

    private function assertTableExists(): void
    {
        if (! Schema::hasTable('abyip_submission_schedules')) {
            throw ValidationException::withMessages([
                'schedule' => ['ABYIP schedule table is not available yet. Run migrations.'],
            ]);
        }
    }

    private function assertNoDuplicateYear(int $fiscalYear, int|string|null $exceptId = null): void
    {
        $query = AbyipSubmissionSchedule::query()->where('fiscal_year', $fiscalYear);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'fiscal_year' => ['A schedule for this calendar year already exists.'],
            ]);
        }
    }

    private function assertEditable(AbyipSubmissionSchedule $schedule): void
    {
        if ($schedule->status === AbyipSubmissionSchedule::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'schedule' => ['Cancelled schedules cannot be edited.'],
            ]);
        }
    }

    private function assertExtendable(AbyipSubmissionSchedule $schedule): void
    {
        if ($schedule->status === AbyipSubmissionSchedule::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'schedule' => ['Cancelled schedules cannot be extended.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateSchedulePayload(array $data, int|string|null $exceptId = null): array
    {
        $tz = config('app.timezone', 'Asia/Manila');
        $currentYear = (int) Carbon::now($tz)->format('Y');
        $expectedFiscalYear = $currentYear;
        $title = trim((string) ($data['title'] ?? 'ABYIP Submission'));
        $dateStart = (string) ($data['date_start'] ?? '');
        $deadline = (string) ($data['deadline'] ?? '');

        $fiscalYear = $exceptId === null
            ? $expectedFiscalYear
            : (int) ($data['fiscal_year'] ?? $expectedFiscalYear);

        if ($fiscalYear !== $expectedFiscalYear) {
            throw ValidationException::withMessages([
                'fiscal_year' => ['Calendar year must be '.$currentYear.' (current year).'],
            ]);
        }

        if ($title === '') {
            throw ValidationException::withMessages(['title' => ['Title is required.']]);
        }

        if (mb_strlen($title) > 50) {
            throw ValidationException::withMessages(['title' => ['Title must not exceed 50 characters.']]);
        }

        if ($dateStart === '' || $deadline === '') {
            throw ValidationException::withMessages(['deadline' => ['Start date and deadline are required.']]);
        }

        $today = Carbon::now($tz)->startOfDay();
        $yearEnd = Carbon::create($currentYear, 12, 31, 0, 0, 0, $tz)->startOfDay();
        $start = Carbon::parse($dateStart, $tz)->startOfDay();
        $end = Carbon::parse($deadline, $tz)->startOfDay();

        if ($start->year !== $currentYear || $end->year !== $currentYear) {
            throw ValidationException::withMessages([
                'date_start' => ['Start date and deadline must fall within the current calendar year ('.$currentYear.').'],
            ]);
        }

        if ($start->lt($today)) {
            throw ValidationException::withMessages([
                'date_start' => ['Start date cannot be earlier than today.'],
            ]);
        }

        if ($start->gt($yearEnd)) {
            throw ValidationException::withMessages([
                'date_start' => ['Start date cannot be later than December 31, '.$currentYear.'.'],
            ]);
        }

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['deadline' => ['Deadline must be on or after the start date.']]);
        }

        if ($end->gt($yearEnd)) {
            throw ValidationException::withMessages([
                'deadline' => ['Deadline cannot be later than December 31, '.$currentYear.'.'],
            ]);
        }

        if ($exceptId === null) {
            $this->assertNoDuplicateYear($fiscalYear);
        } else {
            $this->assertNoDuplicateYear($fiscalYear, $exceptId);
        }

        return [
            'fiscal_year' => $fiscalYear,
            'title' => $title,
            'date_start' => $start->toDateString(),
            'deadline' => $end->toDateString(),
            'allow_late_extension' => filter_var($data['allow_late_extension'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function resolveStatus(string $dateStart, string $deadline, ?AbyipSubmissionSchedule $existing = null): string
    {
        $tz = config('app.timezone', 'Asia/Manila');
        $today = Carbon::now($tz)->startOfDay();
        $start = Carbon::parse($dateStart, $tz)->startOfDay();
        $end = Carbon::parse($deadline, $tz)->startOfDay();

        if ($existing?->status === AbyipSubmissionSchedule::STATUS_EXTENDED && $end->gte($today)) {
            return AbyipSubmissionSchedule::STATUS_EXTENDED;
        }

        if ($today->lt($start)) {
            return AbyipSubmissionSchedule::STATUS_UPCOMING;
        }

        if ($today->lte($end)) {
            return AbyipSubmissionSchedule::STATUS_ONGOING;
        }

        return AbyipSubmissionSchedule::STATUS_COMPLETED;
    }

    private function recordHistory(
        AbyipSubmissionSchedule $schedule,
        string $action,
        $oldDeadline,
        $newDeadline,
        $oldStart,
        $newStart,
        ?string $reason,
        User $user
    ): void {
        if (! $this->historyTableExists()) {
            return;
        }

        AbyipSubmissionScheduleHistory::query()->create([
            'schedule_id' => $schedule->id,
            'action' => $action,
            'old_deadline' => $oldDeadline,
            'new_deadline' => $newDeadline,
            'old_date_start' => $oldStart,
            'new_date_start' => $newStart,
            'reason' => $reason,
            'updated_by_user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function logScheduleEvent(User $user, string $eventType, AbyipSubmissionSchedule $schedule, array $extra = []): void
    {
        $this->auditLog->log($eventType, $user, array_merge([
            'action' => $extra['action'] ?? 'schedule_update',
            'entity_type' => 'abyip_submission_schedule',
            'entity_id' => (string) $schedule->id,
            'module' => 'barangay_monitoring',
            'fiscal_year' => $schedule->fiscal_year,
            'original_deadline' => $schedule->original_deadline?->format('M j, Y'),
            'current_deadline' => $schedule->deadline?->format('M j, Y'),
        ], $extra));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSchedule(AbyipSubmissionSchedule $schedule, bool $includeHistory = false): array
    {
        $data = [
            'id' => $schedule->id,
            'fiscal_year' => $schedule->fiscal_year,
            'title' => $schedule->title,
            'date_start' => $schedule->date_start?->format('M j, Y'),
            'date_start_raw' => $schedule->date_start?->toDateString(),
            'deadline' => $schedule->deadline?->format('M j, Y'),
            'deadline_raw' => $schedule->deadline?->toDateString(),
            'original_deadline' => $schedule->original_deadline?->format('M j, Y'),
            'original_deadline_raw' => $schedule->original_deadline?->toDateString(),
            'status' => $schedule->status,
            'status_label' => $this->statusLabel($schedule->status),
            'allow_late_extension' => (bool) $schedule->allow_late_extension,
            'created_by' => $schedule->creator?->name ?? '—',
            'created_by_role' => $this->creatorRoleLabel($schedule->creator),
            'updated_by' => $schedule->updater?->name ?? '—',
            'cancelled_at' => $schedule->cancelled_at?->format('M j, Y g:i A'),
            'cancellation_reason' => $schedule->cancellation_reason,
        ];

        if ($includeHistory) {
            $data['histories'] = ($this->historyTableExists() && $schedule->relationLoaded('histories'))
                ? $schedule->histories
                    ->map(fn (AbyipSubmissionScheduleHistory $history) => [
                        'action' => $history->action,
                        'action_label' => ucfirst(str_replace('_', ' ', $history->action)),
                        'old_deadline' => $history->old_deadline?->format('M j, Y'),
                        'new_deadline' => $history->new_deadline?->format('M j, Y'),
                        'old_date_start' => $history->old_date_start?->format('M j, Y'),
                        'new_date_start' => $history->new_date_start?->format('M j, Y'),
                        'reason' => $history->reason,
                        'updated_by' => $history->updater?->name ?? '—',
                        'created_at' => $history->created_at?->format('M j, Y g:i A'),
                    ])
                    ->values()
                    ->all()
                : [];
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function scheduleRelations(bool $includeHistory = false): array
    {
        $with = [];

        if (Schema::hasColumn('abyip_submission_schedules', 'created_by_user_id')) {
            $with[] = 'creator:id,name';
            $with[] = 'creator.officialProfile:id,user_id,federation_position,position';
        }

        if (Schema::hasColumn('abyip_submission_schedules', 'updated_by_user_id')) {
            $with[] = 'updater:id,name';
        }

        if ($includeHistory && $this->historyTableExists()) {
            $with[] = 'histories.updater:id,name';
        }

        return $with;
    }

    private function historyTableExists(): bool
    {
        return Schema::hasTable('abyip_submission_schedule_histories');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            AbyipSubmissionSchedule::STATUS_EXTENDED => 'Extended Submission Period',
            AbyipSubmissionSchedule::STATUS_UPCOMING => 'Upcoming',
            AbyipSubmissionSchedule::STATUS_ONGOING => 'Ongoing',
            AbyipSubmissionSchedule::STATUS_COMPLETED => 'Completed',
            AbyipSubmissionSchedule::STATUS_CANCELLED => 'Cancelled',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    private function creatorRoleLabel(?User $user): string
    {
        if ($user === null) {
            return '—';
        }

        if ($user->isSkFed()) {
            return 'SK Federation';
        }

        $user->loadMissing('officialProfile');
        $federationPosition = trim((string) ($user->officialProfile?->federation_position ?? ''));

        if ($federationPosition !== '') {
            return $federationPosition;
        }

        $position = trim((string) ($user->officialProfile?->position ?? ''));

        return $position !== '' ? $position : '—';
    }
}
