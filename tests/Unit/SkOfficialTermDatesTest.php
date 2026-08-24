<?php

use App\Modules\Accounts\Support\SkOfficialTermDates;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('uses november 30 2023 as the ra 11935 term start', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13'));

    expect(SkOfficialTermDates::allowedStartDates())->toBe(['2023-11-30'])
        ->and(SkOfficialTermDates::currentStart())->toBe('2023-11-30')
        ->and(SkOfficialTermDates::termEndForStart('2023-11-30')->toDateString())->toBe('2026-11-30')
        ->and(SkOfficialTermDates::isValidPair('2023-11-30', '2026-11-30'))->toBeTrue();
});

it('rejects december 1 as the 2023 sk term start', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13'));

    expect(SkOfficialTermDates::isValidStart('2023-12-01'))->toBeFalse()
        ->and(SkOfficialTermDates::errorsFor('2023-12-01', '2026-12-01'))
        ->toHaveKey('term_start');
});

it('requires term end to be november 30 three years later', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13'));

    expect(SkOfficialTermDates::errorsFor('2023-11-30', '2026-12-01'))
        ->toHaveKey('term_end')
        ->and(SkOfficialTermDates::errorsFor('2023-11-30', '2027-11-30'))
        ->toHaveKey('term_end');
});

it('unlocks the next november 30 cycle on the assumption date', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-30'));

    expect(SkOfficialTermDates::allowedStartDates())->toBe(['2023-11-30', '2026-11-30'])
        ->and(SkOfficialTermDates::isValidPair('2026-11-30', '2029-11-30'))->toBeTrue();
});
