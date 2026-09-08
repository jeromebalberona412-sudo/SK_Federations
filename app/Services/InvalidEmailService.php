<?php

namespace App\Services;

use App\Models\InvalidEmailAddress;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvalidEmailService
{
    public function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    public function find(string $email): ?InvalidEmailAddress
    {
        if (! $this->tableReady()) {
            return null;
        }

        $normalized = $this->normalize($email);

        return InvalidEmailAddress::query()
            ->where('normalized_email', $normalized)
            ->first();
    }

    /**
     * @return array{
     *     allowed: bool,
     *     status: string,
     *     message: string|null,
     *     retry_after: CarbonInterface|null,
     *     reason: string|null
     * }
     */
    public function checkBeforeSending(string $email): array
    {
        $normalized = $this->normalize($email);
        $record = $this->find($normalized);

        if ($record === null) {
            return $this->allowedResult('active');
        }

        if ($record->isPermanentlyBlocked()) {
            return [
                'allowed' => false,
                'status' => InvalidEmailAddress::STATUS_PERMANENTLY_BLOCKED,
                'message' => 'This email address is invalid and cannot receive mail. Please use another email address.',
                'retry_after' => null,
                'reason' => $record->last_failure_reason,
            ];
        }

        if ($record->isTemporarilyBlocked()) {
            return [
                'allowed' => false,
                'status' => InvalidEmailAddress::STATUS_TEMPORARILY_INVALID,
                'message' => $this->temporaryBlockMessage($record->retry_after),
                'retry_after' => $record->retry_after,
                'reason' => $record->last_failure_reason,
            ];
        }

        return $this->allowedResult(
            $record->status === InvalidEmailAddress::STATUS_VERIFIED
                ? InvalidEmailAddress::STATUS_VERIFIED
                : InvalidEmailAddress::STATUS_ACTIVE,
            $record->retry_after,
            $record->last_failure_reason
        );
    }

    public function recordPermanentFailure(string $email, ?string $reason = null, ?string $notes = null): ?InvalidEmailAddress
    {
        if (! $this->tableReady()) {
            return null;
        }

        $normalized = $this->normalize($email);
        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $record = $this->find($normalized);
        $now = now();
        $reasonLabel = $this->resolveReasonLabel($reason);

        if ($record === null) {
            return InvalidEmailAddress::query()->create([
                'email' => $normalized,
                'normalized_email' => $normalized,
                'status' => InvalidEmailAddress::STATUS_TEMPORARILY_INVALID,
                'failure_count' => 1,
                'last_failure_reason' => $reasonLabel,
                'first_detected_at' => $now,
                'last_detected_at' => $now,
                'retry_after' => $now->copy()->addHours($this->cooldownHoursForFailure(1)),
                'notes' => $notes,
            ]);
        }

        if ($record->isPermanentlyBlocked()) {
            $record->forceFill([
                'last_failure_reason' => $reasonLabel,
                'last_detected_at' => $now,
                'notes' => $notes ?? $record->notes,
            ])->save();

            return $record->fresh() ?? $record;
        }

        $nextCount = max(1, (int) $record->failure_count) + 1;
        $record->forceFill([
            'email' => $normalized,
            'status' => InvalidEmailAddress::STATUS_TEMPORARILY_INVALID,
            'failure_count' => $nextCount,
            'last_failure_reason' => $reasonLabel,
            'first_detected_at' => $record->first_detected_at ?? $now,
            'last_detected_at' => $now,
            'retry_after' => $now->copy()->addHours($this->cooldownHoursForFailure($nextCount)),
            'notes' => $notes ?? $record->notes,
            'permanently_blocked_at' => null,
        ])->save();

        return $record->fresh() ?? $record;
    }

    public function markTemporarilyInvalid(string $email, ?string $reason = null, ?string $notes = null): ?InvalidEmailAddress
    {
        return $this->recordPermanentFailure($email, $reason, $notes);
    }

    /**
     * Send mail synchronously; on permanent recipient failure, store in invalid_email_addresses and reject.
     *
     * @param  callable(): mixed  $send
     *
     * @throws ValidationException
     */
    public function attemptMailDelivery(string $email, callable $send, string $field = 'email'): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            if (! $this->isPermanentDeliveryFailure($exception)) {
                throw $exception;
            }

            $this->recordPermanentFailure(
                $email,
                'permanent_delivery_failure',
                $exception->getMessage()
            );

            $check = $this->checkBeforeSending($email);

            throw ValidationException::withMessages([
                $field => [
                    $check['message']
                        ?? 'This email address is invalid and cannot receive mail. Please use another email address.',
                ],
            ]);
        }
    }

    /**
     * Record permanent failure without blocking the caller (for non-critical confirmation emails).
     */
    public function recordFailureFromException(string $email, Throwable $exception): void
    {
        if (! $this->isPermanentDeliveryFailure($exception)) {
            return;
        }

        $this->recordPermanentFailure(
            $email,
            'permanent_delivery_failure',
            $exception->getMessage()
        );
    }

    public function resetToActive(string $email): ?InvalidEmailAddress
    {
        if (! $this->tableReady()) {
            return null;
        }

        $record = $this->find($email);
        if ($record === null) {
            return null;
        }

        $record->forceFill([
            'status' => InvalidEmailAddress::STATUS_ACTIVE,
            'failure_count' => 0,
            'retry_after' => null,
            'last_failure_reason' => null,
            'permanently_blocked_at' => null,
        ])->save();

        return $record->fresh() ?? $record;
    }

    public function markVerified(string $email): ?InvalidEmailAddress
    {
        if (! $this->tableReady()) {
            return null;
        }

        $normalized = $this->normalize($email);
        $record = $this->find($normalized);

        if ($record === null) {
            return InvalidEmailAddress::query()->create([
                'email' => $normalized,
                'normalized_email' => $normalized,
                'status' => InvalidEmailAddress::STATUS_VERIFIED,
                'failure_count' => 0,
                'last_failure_reason' => null,
                'first_detected_at' => now(),
                'last_detected_at' => now(),
                'retry_after' => null,
                'permanently_blocked_at' => null,
            ]);
        }

        if ($record->isPermanentlyBlocked()) {
            return $record;
        }

        $record->forceFill([
            'status' => InvalidEmailAddress::STATUS_VERIFIED,
            'failure_count' => 0,
            'retry_after' => null,
            'last_failure_reason' => null,
            'last_detected_at' => now(),
            'permanently_blocked_at' => null,
        ])->save();

        return $record->fresh() ?? $record;
    }

    public function permanentlyBlock(string $email, ?string $reason = null, ?string $notes = null): ?InvalidEmailAddress
    {
        if (! $this->tableReady()) {
            return null;
        }

        $normalized = $this->normalize($email);
        $record = $this->find($normalized);
        $now = now();
        $reasonLabel = $this->resolveReasonLabel($reason);

        if ($record === null) {
            return InvalidEmailAddress::query()->create([
                'email' => $normalized,
                'normalized_email' => $normalized,
                'status' => InvalidEmailAddress::STATUS_PERMANENTLY_BLOCKED,
                'failure_count' => 1,
                'last_failure_reason' => $reasonLabel,
                'first_detected_at' => $now,
                'last_detected_at' => $now,
                'retry_after' => null,
                'permanently_blocked_at' => $now,
                'notes' => $notes,
            ]);
        }

        $record->forceFill([
            'status' => InvalidEmailAddress::STATUS_PERMANENTLY_BLOCKED,
            'last_failure_reason' => $reasonLabel,
            'last_detected_at' => $now,
            'retry_after' => null,
            'permanently_blocked_at' => $now,
            'notes' => $notes ?? $record->notes,
        ])->save();

        return $record->fresh() ?? $record;
    }

    public function remove(string $email): bool
    {
        if (! $this->tableReady()) {
            return false;
        }

        $record = $this->find($email);
        if ($record === null) {
            return false;
        }

        return (bool) $record->delete();
    }

    public function isPermanentDeliveryFailure(Throwable $exception): bool
    {
        $haystack = strtolower($exception->getMessage());
        $markers = config('invalid_email.permanent_failure_markers', []);

        foreach ($markers as $marker) {
            if (is_string($marker) && $marker !== '' && str_contains($haystack, strtolower($marker))) {
                return true;
            }
        }

        return false;
    }

    public function cooldownHoursForFailure(int $failureCount): int
    {
        $hours = config('invalid_email.cooldowns_hours', [24, 72, 168, 720]);
        if (! is_array($hours) || $hours === []) {
            $hours = [24, 72, 168, 720];
        }

        $index = max(0, min(count($hours) - 1, $failureCount - 1));

        return max(1, (int) ($hours[$index] ?? 24));
    }

    private function resolveReasonLabel(?string $reason): string
    {
        $reasons = config('invalid_email.failure_reasons', []);
        if (is_string($reason) && isset($reasons[$reason])) {
            return (string) $reasons[$reason];
        }

        if (is_string($reason) && trim($reason) !== '') {
            return trim($reason);
        }

        return (string) ($reasons['permanent_delivery_failure'] ?? 'Permanent Delivery Failure');
    }

    /**
     * @return array{allowed: bool, status: string, message: null, retry_after: CarbonInterface|null, reason: string|null}
     */
    private function allowedResult(string $status, ?CarbonInterface $retryAfter = null, ?string $reason = null): array
    {
        return [
            'allowed' => true,
            'status' => $status,
            'message' => null,
            'retry_after' => $retryAfter,
            'reason' => $reason,
        ];
    }

    private function temporaryBlockMessage(?CarbonInterface $retryAfter): string
    {
        if ($retryAfter !== null) {
            $when = $retryAfter->timezone(config('app.timezone'))->format('M j, Y g:i A');

            return 'This email address is invalid and previously failed delivery. You can try again after '.$when.', or use another email address.';
        }

        return 'This email address is invalid and previously failed delivery. Please try again later or use another email address.';
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('invalid_email_addresses');
        } catch (Throwable) {
            return false;
        }
    }
}
