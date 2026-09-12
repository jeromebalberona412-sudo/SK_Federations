<?php

use App\Modules\Accounts\Support\SkOfficialTermDates;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

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

it('calculates birthdate bounds for age 18 to 30 on October 30 2023 election date', function () {
    $bounds = SkOfficialTermDates::birthdateBounds('2023-10-30');

    expect($bounds['min'])->toBe('1993-10-30')
        ->and($bounds['max'])->toBe('2005-10-30');

    // Exactly 18 on election date (born 2005-10-30) -> eligible
    expect(SkOfficialTermDates::calculateAgeOnElection('2005-10-30', '2023-10-30'))->toBe(18)
        ->and(SkOfficialTermDates::isEligibleElectionAge('2005-10-30', '2023-10-30'))->toBeTrue();

    // 17 on election date (born 2005-10-31) -> ineligible
    expect(SkOfficialTermDates::calculateAgeOnElection('2005-10-31', '2023-10-30'))->toBe(17)
        ->and(SkOfficialTermDates::isEligibleElectionAge('2005-10-31', '2023-10-30'))->toBeFalse();

    // Exactly 30 on election date (born 1993-10-30) -> eligible
    expect(SkOfficialTermDates::calculateAgeOnElection('1993-10-30', '2023-10-30'))->toBe(30)
        ->and(SkOfficialTermDates::isEligibleElectionAge('1993-10-30', '2023-10-30'))->toBeTrue();

    // 31 on election date (born 1992-10-30) -> ineligible
    expect(SkOfficialTermDates::calculateAgeOnElection('1992-10-30', '2023-10-30'))->toBe(31)
        ->and(SkOfficialTermDates::isEligibleElectionAge('1992-10-30', '2023-10-30'))->toBeFalse();
});

it('dynamically computes real-time age on OfficialProfile from date_of_birth', function () {
    $profile = new \App\Modules\Accounts\Models\OfficialProfile;
    $profile->date_of_birth = '1999-05-15';

    $expectedAge = \Carbon\Carbon::parse('1999-05-15')->age;
    expect($profile->age)->toBe($expectedAge)
        ->and($profile->ageOnElection())->toBe(24);
});

it('validates StoreAccountRequest rules for election birthdate bounds and real-time age', function () {
    $bounds = SkOfficialTermDates::birthdateBounds('2023-10-30');
    $dobMin = $bounds['min']; // 1993-10-30
    $dobMax = $bounds['max']; // 2005-10-30

    $rules = [
        'date_of_birth' => ['required', 'date', 'before:today', 'after_or_equal:'.$dobMin, 'before_or_equal:'.$dobMax],
        'age' => ['required', 'integer', 'min:1', 'max:150'],
    ];

    // Eligible candidate: born 2000-05-15 (currently age 26 in 2026, was 23 on election day)
    $validData = [
        'date_of_birth' => '2000-05-15',
        'age' => Carbon::parse('2000-05-15')->age,
    ];
    $validator = validator($validData, $rules);
    expect($validator->passes())->toBeTrue();

    // Ineligible candidate: born 1992-10-30 (turned 31 on election day)
    $tooOldData = [
        'date_of_birth' => '1992-10-30',
        'age' => Carbon::parse('1992-10-30')->age,
    ];
    $validator = validator($tooOldData, $rules);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('date_of_birth'))->toBeTrue();

    // Ineligible candidate: born 2005-10-31 (turned 18 after election day)
    $tooYoungData = [
        'date_of_birth' => '2005-10-31',
        'age' => Carbon::parse('2005-10-31')->age,
    ];
    $validator = validator($tooYoungData, $rules);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('date_of_birth'))->toBeTrue();
});

it('calculates dynamic birthdate bounds based on term start date', function () {
    $bounds2024 = SkOfficialTermDates::birthdateBounds('2024-01-15');
    expect($bounds2024['min'])->toBe('1994-01-15')
        ->and($bounds2024['max'])->toBe('2006-01-15');

    $bounds2026 = SkOfficialTermDates::birthdateBounds('2026-11-01');
    expect($bounds2026['min'])->toBe('1996-11-01')
        ->and($bounds2026['max'])->toBe('2008-11-01');
});
