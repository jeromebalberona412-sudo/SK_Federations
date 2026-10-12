<?php

use App\Modules\AuditLog\Support\AuditLogPresenter;
use App\Modules\AuditLog\Models\AdminActivityLog;
use App\Modules\Shared\Models\User;
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

it('includes an account email from a loaded audit user relation', function () {
    $user = new User;
    $user->forceFill(['email' => 'official@example.com', 'name' => 'SK Official', 'role' => 'sk_fed']);
    $log = new AdminActivityLog;
    $log->forceFill([
        'id' => 'audit-row',
        'event_type' => 'login_success',
        'metadata' => ['email' => 'stale@example.com'],
    ]);
    $log->setRelation('user', $user);

    expect(AuditLogPresenter::toArray($log)['account_email'])->toBe('official@example.com');
});

it('does not treat an unlinked attempted email as an account email', function () {
    $log = new AdminActivityLog;
    $log->forceFill([
        'id' => 'audit-row',
        'event_type' => 'login_failed',
        'metadata' => ['email' => 'unknown@example.com'],
    ]);

    expect(AuditLogPresenter::toArray($log)['account_email'])->toBeNull();
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
