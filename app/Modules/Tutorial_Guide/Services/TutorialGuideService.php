<?php

namespace App\Modules\Tutorial_Guide\Services;

use App\Modules\Shared\Models\User;
use App\Modules\Tutorial_Guide\Models\SkFederationTutorial;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class TutorialGuideService
{
    /**
     * Get or create the tutorial progress record for the user.
     */
    public function getOrCreateTutorial(User $user, string $tutorialKey = SkFederationTutorial::DEFAULT_TUTORIAL_KEY): SkFederationTutorial
    {
        /** @var SkFederationTutorial $record */
        $record = SkFederationTutorial::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'tutorial_key' => $tutorialKey,
            ],
            [
                'current_step' => 1,
                'status' => SkFederationTutorial::STATUS_NOT_STARTED,
            ]
        );

        return $record;
    }

    /**
     * Determine whether the tutorial should be automatically launched on page load.
     */
    public function shouldAutoStart(User $user, string $tutorialKey = SkFederationTutorial::DEFAULT_TUTORIAL_KEY): bool
    {
        $record = SkFederationTutorial::query()
            ->where('user_id', $user->id)
            ->where('tutorial_key', $tutorialKey)
            ->first();

        if (! $record) {
            return true;
        }

        return $record->shouldAutoStart();
    }

    /**
     * Mark the tutorial as started / in progress.
     */
    public function startTutorial(User $user, string $tutorialKey = SkFederationTutorial::DEFAULT_TUTORIAL_KEY): SkFederationTutorial
    {
        try {
            $tutorial = $this->getOrCreateTutorial($user, $tutorialKey);

            if ($tutorial->status !== SkFederationTutorial::STATUS_COMPLETED) {
                $tutorial->update([
                    'status' => SkFederationTutorial::STATUS_IN_PROGRESS,
                    'started_at' => $tutorial->started_at ?? now(),
                    'last_seen_at' => now(),
                ]);
            }

            return $tutorial->fresh();
        } catch (Throwable $e) {
            Log::error('Failed to start SK Federation tutorial: '.$e->getMessage(), [
                'user_id' => $user->id,
                'tutorial_key' => $tutorialKey,
            ]);

            throw $e;
        }
    }

    /**
     * Save current progress step for the user.
     */
    public function updateProgress(User $user, int $step, string $tutorialKey = SkFederationTutorial::DEFAULT_TUTORIAL_KEY): SkFederationTutorial
    {
        try {
            $tutorial = $this->getOrCreateTutorial($user, $tutorialKey);

            $step = max(1, $step);

            $tutorial->update([
                'current_step' => $step,
                'status' => $tutorial->status === SkFederationTutorial::STATUS_COMPLETED
                    ? SkFederationTutorial::STATUS_COMPLETED
                    : SkFederationTutorial::STATUS_IN_PROGRESS,
                'last_seen_at' => now(),
            ]);

            return $tutorial->fresh();
        } catch (Throwable $e) {
            Log::error('Failed to update SK Federation tutorial progress: '.$e->getMessage(), [
                'user_id' => $user->id,
                'step' => $step,
            ]);

            throw $e;
        }
    }

    /**
     * Mark the tutorial as successfully completed.
     */
    public function completeTutorial(User $user, string $tutorialKey = SkFederationTutorial::DEFAULT_TUTORIAL_KEY): SkFederationTutorial
    {
        try {
            $tutorial = $this->getOrCreateTutorial($user, $tutorialKey);

            $tutorial->update([
                'status' => SkFederationTutorial::STATUS_COMPLETED,
                'completed_at' => now(),
                'last_seen_at' => now(),
            ]);

            return $tutorial->fresh();
        } catch (Throwable $e) {
            Log::error('Failed to complete SK Federation tutorial: '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);

            throw $e;
        }
    }

    /**
     * Mark the tutorial as skipped by the user.
     * First-run is mandatory — skip is only allowed after a prior complete/skip.
     */
    public function skipTutorial(User $user, string $tutorialKey = SkFederationTutorial::DEFAULT_TUTORIAL_KEY): SkFederationTutorial
    {
        try {
            $tutorial = $this->getOrCreateTutorial($user, $tutorialKey);

            if (! $tutorial->hasFinishedMandatoryOnce()) {
                throw ValidationException::withMessages([
                    'tutorial' => 'Please finish the tutorial. Skip is available after your first completion.',
                ]);
            }

            // Replay dismiss: keep COMPLETED (or prior SKIPPED); do not downgrade mandatory success.
            $tutorial->update([
                'last_seen_at' => now(),
            ]);

            return $tutorial->fresh();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to skip SK Federation tutorial: '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);

            throw $e;
        }
    }

    /**
     * Reset step for a manual restart from the header button.
     * Preserves completed_at/skipped_at so first-run stays done and auto-start stays off.
     */
    public function resetTutorial(User $user, string $tutorialKey = SkFederationTutorial::DEFAULT_TUTORIAL_KEY): SkFederationTutorial
    {
        try {
            $tutorial = $this->getOrCreateTutorial($user, $tutorialKey);

            $updates = [
                'current_step' => 1,
                'last_seen_at' => now(),
            ];

            if (! $tutorial->hasFinishedMandatoryOnce()) {
                $updates['status'] = SkFederationTutorial::STATUS_IN_PROGRESS;
                $updates['started_at'] = $tutorial->started_at ?? now();
            }

            $tutorial->update($updates);

            return $tutorial->fresh();
        } catch (Throwable $e) {
            Log::error('Failed to reset SK Federation tutorial: '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);

            throw $e;
        }
    }

    /**
     * Format tutorial data for frontend JSON response.
     *
     * @return array<string, mixed>
     */
    public function formatTutorialPayload(SkFederationTutorial $tutorial): array
    {
        $finishedOnce = $tutorial->hasFinishedMandatoryOnce();

        return [
            'tutorial_key' => $tutorial->tutorial_key,
            'current_step' => (int) $tutorial->current_step,
            'status' => $tutorial->status,
            'should_auto_start' => $tutorial->shouldAutoStart(),
            'is_completed' => $tutorial->isCompleted(),
            'is_skipped' => $tutorial->isSkipped(),
            'can_skip' => $finishedOnce,
            'is_mandatory' => ! $finishedOnce,
        ];
    }
}
