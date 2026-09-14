<?php

use Tests\TestCase;

uses(TestCase::class);

function dsoIntegrityServiceSource(): string
{
    return (string) file_get_contents(base_path('app/Modules/Deleted_SK_Officials/Services/DeletedSkOfficialsService.php'));
}

function dsoIntegrityControllerSource(): string
{
    return (string) file_get_contents(base_path('app/Modules/Deleted_SK_Officials/Controllers/DeletedSkOfficialsController.php'));
}

it('never creates a new user during restore', function () {
    $source = dsoIntegrityServiceSource();

    expect($source)->toContain('Original account was not found. Restore cannot continue.')
        ->and($source)->toContain('Original account identifier is missing. Restore cannot continue.')
        ->and($source)->toContain('This account is already active in Manage Accounts.')
        ->and($source)->not->toContain('User::query()->create([');
});

it('blocks restore on email conflict and roster limits', function () {
    $source = dsoIntegrityServiceSource();

    expect($source)->toContain('another active account already uses this email address')
        ->and($source)->toContain('assertRosterLimits(')
        ->and($source)->toContain('Cannot restore:');
});

it('reuses an unrecovered archive row instead of inserting duplicates', function () {
    $source = dsoIntegrityServiceSource();

    expect($source)->toContain("whereNull('restored_at')")
        ->and($source)->toContain('original_user_id')
        ->and($source)->toContain('$existing->fill($payload)->save()')
        ->and($source)->toContain('DeletedSkOfficial::query()->create($payload)');
});

it('returns validation failures as JSON 422 from the restore endpoint', function () {
    $source = dsoIntegrityControllerSource();

    expect($source)->toContain('ValidationException')
        ->and($source)->toContain(', 422)');
});
