<?php

use App\Modules\AuditLog\Support\AuditLogPresenter;
use Tests\TestCase;

uses(TestCase::class);

it('resolves audit emails from metadata when the related user is missing', function () {
    $email = AuditLogPresenter::resolveUserEmail(
        null,
        (object) [],
        ['email' => 'youth@example.com']
    );

    expect($email)->toBe('youth@example.com');
});

it('prefers the related user email over metadata', function () {
    $email = AuditLogPresenter::resolveUserEmail(
        (object) ['email' => 'official@example.com'],
        (object) [],
        ['email' => 'stale@example.com']
    );

    expect($email)->toBe('official@example.com');
});

it('defaults federations dashboard from and to to the current calendar year within official terms', function () {
    $blade = file_get_contents(base_path('app/Modules/Dashboard/Views/dashboard.blade.php'));
    $controller = file_get_contents(base_path('app/Modules/Dashboard/Controllers/DashboardController.php'));

    expect($blade)
        ->toContain('data-default-from="{{ $dashDateFrom }}"')
        ->toContain('data-term-start="{{ $dashTermStart }}"')
        ->toContain('min="{{ $dashTermStart }}"')
        ->toContain('max="{{ $dashTermEnd }}"')
        ->toContain('Reset to January 1 – December 31 of the current year')
        ->and($controller)->toContain('January 1 → December 31 of the current year')
        ->and($controller)->toContain('Selectable date-picker bounds from official_terms.term_start / term_end');
});
