<?php

namespace App\Modules\KabataanMonitoring\Support;

use App\Models\KabataanRegistration;
use Illuminate\Database\Eloquent\Builder;

final class KkProfilingReviewStatus
{
    private const REJECTED_REGISTRATION_STATUSES = ['rejected', 'archived'];

    private const UNVERIFIED_ACCOUNT_STATUSES = ['pending_verification', 'email_verified'];

    private const APPROVED_EVALUATION_STATUSES = ['active', 'Auto Approved', 'ID Verified'];

    private const PENDING_EVALUATION_STATUSES = ['Not Profiled', 'Wrong Credentials'];

    private const REJECTED_EVALUATION_STATUSES = ['Duplicate'];

    /**
     * @param  Builder<KabataanRegistration>  $query
     * @return Builder<KabataanRegistration>
     */
    public static function applyFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'pending' => self::applyPendingScope($query),
            'approved' => self::applyApprovedScope($query),
            'rejected' => self::applyRejectedScope($query),
            default => $query,
        };
    }

    /**
     * @param  Builder<KabataanRegistration>  $query
     * @return Builder<KabataanRegistration>
     */
    public static function applyPendingScope(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', self::REJECTED_REGISTRATION_STATUSES)
            ->where(function (Builder $evaluation): void {
                $evaluation
                    ->whereNull('evaluation_status')
                    ->orWhere('evaluation_status', '')
                    ->orWhereNotIn('evaluation_status', self::REJECTED_EVALUATION_STATUSES);
            })
            ->where(function (Builder $pending): void {
                $pending
                    ->whereIn('status', self::UNVERIFIED_ACCOUNT_STATUSES)
                    ->orWhereNull('evaluation_status')
                    ->orWhere('evaluation_status', '')
                    ->orWhereIn('evaluation_status', self::PENDING_EVALUATION_STATUSES);
            });
    }

    /**
     * @param  Builder<KabataanRegistration>  $query
     * @return Builder<KabataanRegistration>
     */
    public static function applyApprovedScope(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', array_merge(
                self::REJECTED_REGISTRATION_STATUSES,
                self::UNVERIFIED_ACCOUNT_STATUSES,
            ))
            ->whereIn('evaluation_status', self::APPROVED_EVALUATION_STATUSES)
            ->where(function (Builder $account): void {
                $account
                    ->where(function (Builder $portal): void {
                        $portal->whereNotNull('user_id')
                            ->whereNotNull('email_verified_at')
                            ->whereNotNull('password_set_at');
                    })
                    ->orWhere(function (Builder $walkIn): void {
                        $walkIn->whereNull('user_id')
                            ->whereNull('email_verified_at')
                            ->whereNull('password_set_at');
                    });
            });
    }

    /**
     * @param  Builder<KabataanRegistration>  $query
     * @return Builder<KabataanRegistration>
     */
    private static function applyRejectedScope(Builder $query): Builder
    {
        return $query->where(function (Builder $rejected): void {
            $rejected
                ->whereIn('status', self::REJECTED_REGISTRATION_STATUSES)
                ->orWhereIn('evaluation_status', self::REJECTED_EVALUATION_STATUSES);
        });
    }

    public static function labelFor(KabataanRegistration $record): string
    {
        $status = strtolower((string) $record->status);
        $evaluation = (string) $record->evaluation_status;

        if (in_array($status, self::REJECTED_REGISTRATION_STATUSES, true)
            || in_array($evaluation, self::REJECTED_EVALUATION_STATUSES, true)) {
            return 'Rejected';
        }

        $approved = in_array($status, self::UNVERIFIED_ACCOUNT_STATUSES, true) === false
            && in_array($evaluation, self::APPROVED_EVALUATION_STATUSES, true)
            && (
                ($record->user_id !== null && $record->email_verified_at !== null && $record->password_set_at !== null)
                || ($record->user_id === null && $record->email_verified_at === null && $record->password_set_at === null)
            );

        if ($approved) {
            return 'Approved';
        }

        if (
            in_array($status, self::UNVERIFIED_ACCOUNT_STATUSES, true)
            || $evaluation === ''
            || in_array($evaluation, self::PENDING_EVALUATION_STATUSES, true)
        ) {
            return 'Pending';
        }

        return $record->evaluation_status ?: ucfirst(str_replace('_', ' ', $record->status));
    }

    public static function isPending(KabataanRegistration $record): bool
    {
        return self::labelFor($record) === 'Pending';
    }
}
