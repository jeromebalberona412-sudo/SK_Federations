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

    expect($bounds['min'])->toBe('1992-10-31')
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
    $dobMin = $bounds['min']; // 1992-10-31
    $dobMax = $bounds['max']; // 2005-10-30

    $rules = [
        'date_of_birth' => ['required', 'date', 'before:today', 'after_or_equal:'.$dobMin, 'before_or_equal:'.$dobMax],
        'age' => ['required', 'integer', 'min:1', 'max:150'],
    ];

    $messages = [
        'date_of_birth.required' => SkOfficialTermDates::MSG_DOB_REQUIRED,
        'date_of_birth.after_or_equal' => SkOfficialTermDates::MSG_OVER_MAX_AGE,
        'date_of_birth.before_or_equal' => SkOfficialTermDates::MSG_UNDER_MIN_AGE,
    ];

    // Eligible candidate: born 2000-05-15 (currently age 26 in 2026, was 23 on election day)
    $validData = [
        'date_of_birth' => '2000-05-15',
        'age' => Carbon::parse('2000-05-15')->age,
    ];
    $validator = validator($validData, $rules, $messages);
    expect($validator->passes())->toBeTrue();

    // Eligible candidate: born 1993-10-29 (30 years old on election day) -> ALLOW
    $valid30 = [
        'date_of_birth' => '1993-10-29',
        'age' => Carbon::parse('1993-10-29')->age,
    ];
    $validator = validator($valid30, $rules, $messages);
    expect($validator->passes())->toBeTrue();

    // Ineligible candidate: born 1992-10-30 (turned 31 on election day) -> BLOCK with exact message
    $tooOldData = [
        'date_of_birth' => '1992-10-30',
        'age' => Carbon::parse('1992-10-30')->age,
    ];
    $validator = validator($tooOldData, $rules, $messages);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('date_of_birth'))->toBe('SK Official must not be older than 30 years old.');

    // Ineligible candidate: born 2005-10-31 (turned 18 after election day) -> BLOCK with exact message
    $tooYoungData = [
        'date_of_birth' => '2005-10-31',
        'age' => Carbon::parse('2005-10-31')->age,
    ];
    $validator = validator($tooYoungData, $rules, $messages);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('date_of_birth'))->toBe('SK Official must be at least 18 years old.');

    // Missing birthdate -> BLOCK with exact message
    $missingData = [
        'date_of_birth' => '',
        'age' => 20,
    ];
    $validator = validator($missingData, $rules, $messages);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('date_of_birth'))->toBe('Birthdate is required.');
});

it('calculates dynamic birthdate bounds based on term start date', function () {
    $bounds2024 = SkOfficialTermDates::birthdateBounds('2024-01-15');
    expect($bounds2024['min'])->toBe('1993-01-16')
        ->and($bounds2024['max'])->toBe('2006-01-15');

    $bounds2026 = SkOfficialTermDates::birthdateBounds('2026-11-01');
    expect($bounds2026['min'])->toBe('1995-11-02')
        ->and($bounds2026['max'])->toBe('2008-11-01');
});

it('satisfies all 12 SK official birthdate age specification test cases on reference date 2023-10-30', function () {
    $ref = '2023-10-30';

    // TEST 1: DOB = October 30, 2005 -> Age = 18 -> ALLOW
    expect(SkOfficialTermDates::ageAt('2005-10-30', $ref))->toBe(18)
        ->and(SkOfficialTermDates::isEligibleElectionAge('2005-10-30', $ref))->toBeTrue()
        ->and(SkOfficialTermDates::classifyBirthdate('2005-10-30', $ref))->toBe('ok')
        ->and(SkOfficialTermDates::birthdateErrorFor('2005-10-30', $ref))->toBeNull();

    // TEST 2: DOB = October 31, 2005 -> Age = 17 -> BLOCK
    expect(SkOfficialTermDates::ageAt('2005-10-31', $ref))->toBe(17)
        ->and(SkOfficialTermDates::isEligibleElectionAge('2005-10-31', $ref))->toBeFalse()
        ->and(SkOfficialTermDates::classifyBirthdate('2005-10-31', $ref))->toBe('under')
        ->and(SkOfficialTermDates::birthdateErrorFor('2005-10-31', $ref))->toBe('SK Official must be at least 18 years old.');

    // TEST 3: DOB = October 29, 2005 -> Age = 18 -> ALLOW
    expect(SkOfficialTermDates::ageAt('2005-10-29', $ref))->toBe(18)
        ->and(SkOfficialTermDates::isEligibleElectionAge('2005-10-29', $ref))->toBeTrue()
        ->and(SkOfficialTermDates::classifyBirthdate('2005-10-29', $ref))->toBe('ok')
        ->and(SkOfficialTermDates::birthdateErrorFor('2005-10-29', $ref))->toBeNull();

    // TEST 4: DOB = October 30, 1993 -> Age = 30 -> ALLOW
    expect(SkOfficialTermDates::ageAt('1993-10-30', $ref))->toBe(30)
        ->and(SkOfficialTermDates::isEligibleElectionAge('1993-10-30', $ref))->toBeTrue()
        ->and(SkOfficialTermDates::classifyBirthdate('1993-10-30', $ref))->toBe('ok')
        ->and(SkOfficialTermDates::birthdateErrorFor('1993-10-30', $ref))->toBeNull();

    // TEST 5: DOB = October 31, 1993 -> Age = 29 -> ALLOW
    expect(SkOfficialTermDates::ageAt('1993-10-31', $ref))->toBe(29)
        ->and(SkOfficialTermDates::isEligibleElectionAge('1993-10-31', $ref))->toBeTrue()
        ->and(SkOfficialTermDates::classifyBirthdate('1993-10-31', $ref))->toBe('ok')
        ->and(SkOfficialTermDates::birthdateErrorFor('1993-10-31', $ref))->toBeNull();

    // TEST 6: DOB = October 29, 1993 -> Age = 30 -> ALLOW
    expect(SkOfficialTermDates::ageAt('1993-10-29', $ref))->toBe(30)
        ->and(SkOfficialTermDates::isEligibleElectionAge('1993-10-29', $ref))->toBeTrue()
        ->and(SkOfficialTermDates::classifyBirthdate('1993-10-29', $ref))->toBe('ok')
        ->and(SkOfficialTermDates::birthdateErrorFor('1993-10-29', $ref))->toBeNull();

    // TEST 7: DOB = October 30, 1992 -> Age = 31 -> BLOCK
    expect(SkOfficialTermDates::ageAt('1992-10-30', $ref))->toBe(31)
        ->and(SkOfficialTermDates::isEligibleElectionAge('1992-10-30', $ref))->toBeFalse()
        ->and(SkOfficialTermDates::classifyBirthdate('1992-10-30', $ref))->toBe('over')
        ->and(SkOfficialTermDates::birthdateErrorFor('1992-10-30', $ref))->toBe('SK Official must not be older than 30 years old.');

    // TEST 8: DOB = January 1, 1992 -> Age = 31 -> BLOCK
    expect(SkOfficialTermDates::ageAt('1992-01-01', $ref))->toBe(31)
        ->and(SkOfficialTermDates::isEligibleElectionAge('1992-01-01', $ref))->toBeFalse()
        ->and(SkOfficialTermDates::classifyBirthdate('1992-01-01', $ref))->toBe('over')
        ->and(SkOfficialTermDates::birthdateErrorFor('1992-01-01', $ref))->toBe('SK Official must not be older than 30 years old.');

    // TEST 9: DOB = exactly 18 years before reference date -> ALLOW
    $exactly18 = Carbon::parse($ref)->subYears(18)->toDateString(); // 2005-10-30
    expect(SkOfficialTermDates::ageAt($exactly18, $ref))->toBe(18)
        ->and(SkOfficialTermDates::isEligibleElectionAge($exactly18, $ref))->toBeTrue();

    // TEST 10: DOB = exactly 30 years before reference date -> ALLOW
    $exactly30 = Carbon::parse($ref)->subYears(30)->toDateString(); // 1993-10-30
    expect(SkOfficialTermDates::ageAt($exactly30, $ref))->toBe(30)
        ->and(SkOfficialTermDates::isEligibleElectionAge($exactly30, $ref))->toBeTrue();

    // TEST 11: DOB = one day younger than the 18-year boundary -> BLOCK
    $oneDayYoungerThan18 = Carbon::parse($exactly18)->addDay()->toDateString(); // 2005-10-31
    expect(SkOfficialTermDates::ageAt($oneDayYoungerThan18, $ref))->toBe(17)
        ->and(SkOfficialTermDates::isEligibleElectionAge($oneDayYoungerThan18, $ref))->toBeFalse()
        ->and(SkOfficialTermDates::classifyBirthdate($oneDayYoungerThan18, $ref))->toBe('under');

    // TEST 12: DOB = one day older than the 30-year boundary (turning 31 on ref date) -> BLOCK
    $oneDayOlderThan30 = Carbon::parse($ref)->subYears(31)->toDateString(); // 1992-10-30
    expect(SkOfficialTermDates::ageAt($oneDayOlderThan30, $ref))->toBe(31)
        ->and(SkOfficialTermDates::isEligibleElectionAge($oneDayOlderThan30, $ref))->toBeFalse()
        ->and(SkOfficialTermDates::classifyBirthdate($oneDayOlderThan30, $ref))->toBe('over');
});

it('returns required validation message when birthdate is missing', function () {
    expect(SkOfficialTermDates::birthdateErrorFor(null))->toBe('Birthdate is required.')
        ->and(SkOfficialTermDates::birthdateErrorFor(''))->toBe('Birthdate is required.')
        ->and(SkOfficialTermDates::birthdateErrorFor('   '))->toBe('Birthdate is required.');
});

it('defaults eligibility to current date when reference date is omitted', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-12'));

    $bounds = SkOfficialTermDates::birthdateBounds();
    expect($bounds['max'])->toBe('2008-09-12')
        ->and($bounds['min'])->toBe('1995-09-13');

    // Exactly 18 today (born 2008-09-12) -> eligible
    expect(SkOfficialTermDates::birthdateErrorFor('2008-09-12'))->toBeNull();

    // 17 today (born 2008-09-13) -> under age
    expect(SkOfficialTermDates::birthdateErrorFor('2008-09-13'))->toBe('SK Official must be at least 18 years old.');

    // Exactly 30 today (born 1996-09-12) -> eligible
    expect(SkOfficialTermDates::birthdateErrorFor('1996-09-12'))->toBeNull();

    // Turned 31 yesterday (born 1995-09-11) -> over age
    expect(SkOfficialTermDates::birthdateErrorFor('1995-09-11'))->toBe('SK Official must not be older than 30 years old.');

    // Born 2006-09-07 (from user screenshot, age 20) -> eligible!
    expect(SkOfficialTermDates::birthdateErrorFor('2006-09-07'))->toBeNull();
});

