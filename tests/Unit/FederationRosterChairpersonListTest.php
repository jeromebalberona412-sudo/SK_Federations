<?php

use App\Modules\Accounts\Services\ChairpersonFederationSyncService;
use App\Modules\Shared\Models\User;
use Tests\TestCase;

uses(TestCase::class);

it('hides ended-term chairpersons from the federation manage roster query', function () {
    $source = file_get_contents(app_path('Modules/Accounts/Services/ChairpersonFederationSyncService.php'));

    expect($source)->toContain('applyFederationRosterMemberConstraint')
        ->and($source)->toContain('applyCurrentOfficialTermConstraint')
        ->and($source)->toContain('currentActive()');
});

it('keeps chairperson position matching for chairperson and chairman variants', function () {
    $service = new ChairpersonFederationSyncService;

    expect($service->isChairpersonPosition('Chairperson'))->toBeTrue()
        ->and($service->isChairpersonPosition('Chairman'))->toBeTrue()
        ->and($service->isChairpersonPosition('SK Chairperson'))->toBeTrue()
        ->and($service->isChairpersonPosition('Secretary'))->toBeFalse();
});

it('applies current active term constraint for manage officials and federation roster', function () {
    $service = new ChairpersonFederationSyncService;

    $roster = User::query();
    $service->applyFederationRosterMemberConstraint($roster);
    $rosterSql = strtolower($roster->toSql());

    expect($roster->getBindings())->toContain(User::ROLE_SK_OFFICIAL)
        ->and($rosterSql)->toContain('exists')
        ->and($rosterSql)->toContain('term_end');

    $officials = User::query();
    $service->applyCurrentOfficialTermConstraint($officials);
    $officialsSql = strtolower($officials->toSql());

    expect($officialsSql)->toContain('term_end')
        ->and($officialsSql)->toContain('exists');
});

it('archives ended terms into both officials and federation records for chairpersons', function () {
    $source = file_get_contents(app_path('Modules/SK_Officials_Records/Services/ExpiredTermProcessorService.php'));

    expect($source)->toContain('archiveEndedTerm')
        ->and($source)->toContain('SkFederationRecordsService')
        ->and($source)->toContain('isChairpersonPosition')
        ->and($source)->toContain('STATUS_EXPIRED')
        ->and($source)->toContain('ROLE_SK_OFFICIAL')
        ->and($source)->toContain('isSkFed()')
        ->and($source)->not->toContain("whereIn('role', [User::ROLE_SK_OFFICIAL, User::ROLE_SK_FED])");
});

it('never treats sk fed administrators as term-ended for login', function () {
    $source = file_get_contents(app_path('Modules/Authentication/Services/AuthenticationService.php'));

    expect($source)->toContain('if ($user->isSkFed())')
        ->and($source)->toContain('return false;');
});
