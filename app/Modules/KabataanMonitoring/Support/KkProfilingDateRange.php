<?php

namespace App\Modules\KabataanMonitoring\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class KkProfilingDateRange
{
    /**
     * Apply an inclusive date range to the timestamp used by each profiling status.
     *
     * @param  'registration'|'approved'|'pending'  $dateType
     * @param  Builder<\App\Models\KabataanRegistration>  $query
     * @return Builder<\App\Models\KabataanRegistration>
     */
    public static function apply(
        Builder $query,
        ?string $dateFrom,
        ?string $dateTo,
        string $dateType = 'approved',
    ): Builder {
        [$from, $to] = self::normalizeRange($dateFrom, $dateTo);
        if ($from === null || $to === null) {
            return $query;
        }

        $columns = match ($dateType) {
            'registration', 'pending' => ['submitted_at', 'created_at'],
            default => ['reviewed_at', 'submitted_at', 'created_at'],
        };
        $columns = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn('kabataan_registrations', $column)
        ));

        if ($columns === []) {
            return $query;
        }

        $start = $from.' 00:00:00';
        $endExclusive = Carbon::createFromFormat('!Y-m-d', $to)->addDay()->toDateString().' 00:00:00';

        return $query->where(function (Builder $range) use ($columns, $start, $endExclusive): void {
            $precedingColumns = [];

            foreach ($columns as $column) {
                $range->orWhere(function (Builder $candidate) use ($precedingColumns, $column, $start, $endExclusive): void {
                    foreach ($precedingColumns as $precedingColumn) {
                        $candidate->whereNull($precedingColumn);
                    }

                    $candidate
                        ->where($column, '>=', $start)
                        ->where($column, '<', $endExclusive);
                });

                $precedingColumns[] = $column;
            }
        });
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function normalizeRange(?string $dateFrom, ?string $dateTo): array
    {
        $from = trim((string) $dateFrom);
        $to = trim((string) $dateTo);

        if ($from === '' && $to === '') {
            return [null, null];
        }

        if ($from === '') {
            $from = $to;
        }
        if ($to === '') {
            $to = $from;
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        try {
            $from = Carbon::createFromFormat('!Y-m-d', $from)->toDateString();
            $to = Carbon::createFromFormat('!Y-m-d', $to)->toDateString();
        } catch (\Throwable) {
            return [null, null];
        }

        return [$from, $to];
    }
}
