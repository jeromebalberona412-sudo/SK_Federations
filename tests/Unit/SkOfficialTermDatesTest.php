<?php

use App\Modules\Accounts\Support\SkOfficialTermDates;
use Carbon\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('defaults term start to today and end to today plus five years', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-31'));

    expect(SkOfficialTermDates::currentStart())->toBe('2026-08-31')
        ->and(SkOfficialTermDates::defaultEnd())->toBe('2031-08-31')
        ->and(SkOfficialTermDates::termEndForStart('2026-08-31')->toDateString())->toBe('2031-08-31')
        ->and(SkOfficialTermDates::minTermEndForStart('2026-08-31')->toDateString())->toBe('2029-08-31')
        ->and(SkOfficialTermDates::maxStart())->toBe('2026-09-30');
});

it('allows term start up to thirty days in the future', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-31'));

    expect(SkOfficialTermDates::errorsFor('2026-09-15', '2031-09-15'))->toBe([])
        ->and(SkOfficialTermDates::errorsFor('2026-10-01', '2031-10-01'))
        ->toHaveKey('term_start');
});

it('allows term end between three and five years after term start', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-31'));

    expect(SkOfficialTermDates::errorsFor('2026-08-31', '2028-08-31'))
        ->toHaveKey('term_end')
        ->and(SkOfficialTermDates::errorsFor('2026-08-31', '2029-08-31'))
        ->toBe([])
        ->and(SkOfficialTermDates::errorsFor('2026-08-31', '2030-08-31'))
        ->toBe([])
        ->and(SkOfficialTermDates::errorsFor('2026-08-31', '2031-08-31'))
        ->toBe([])
        ->and(SkOfficialTermDates::errorsFor('2026-08-31', '2032-08-31'))
        ->toHaveKey('term_end');
});

it('rejects term starts before the earliest allowed date', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-31'));

    expect(SkOfficialTermDates::errorsFor('2022-12-31', '2027-12-31'))
        ->toHaveKey('term_start');
});
