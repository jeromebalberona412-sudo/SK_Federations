<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

it('registers all deleted sk officials routes including show and restore', function () {
    expect(Route::has('archived.deleted-sk-officials'))->toBeTrue()
        ->and(Route::has('deleted-sk-officials'))->toBeTrue()
        ->and(Route::has('archived.deleted-sk-officials.data'))->toBeTrue()
        ->and(Route::has('deleted-sk-officials.data'))->toBeTrue()
        ->and(Route::has('archived.deleted-sk-officials.show'))->toBeTrue()
        ->and(Route::has('deleted-sk-officials.show'))->toBeTrue()
        ->and(Route::has('archived.deleted-sk-officials.restore'))->toBeTrue()
        ->and(Route::has('deleted-sk-officials.restore'))->toBeTrue();
});

it('has the exact 9-column table structure and Actions header in the Blade view', function () {
    $bladeContent = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/Views/deleted-sk-officials.blade.php'));

    // Check table headers: Full Name, Position, Barangay, Municipality, Term, Date Deleted, Time Deleted, Deleted By, Actions
    expect($bladeContent)->toContain('Full Name')
        ->and($bladeContent)->toContain('LN, FN, MN, Suffix')
        ->and($bladeContent)->toContain('<th>Position</th>')
        ->and($bladeContent)->toContain('<th>Barangay</th>')
        ->and($bladeContent)->toContain('<th>Municipality</th>')
        ->and($bladeContent)->toContain('<th>Term</th>')
        ->and($bladeContent)->toContain('<th>Date Deleted</th>')
        ->and($bladeContent)->toContain('<th>Time Deleted</th>')
        ->and($bladeContent)->toContain('<th>Deleted By</th>')
        ->and($bladeContent)->toContain('<th class="dso-col-actions">Actions</th>');

    // Confirm Email Address and Auto-Delete In are not table column headers
    expect($bladeContent)->not->toContain('<th>Email Address</th>')
        ->and($bladeContent)->not->toContain('<th>Auto-Delete In</th>');

    // Colspan for empty rows must be 9
    expect($bladeContent)->toContain('colspan="9"');

    // Confirmation modal title and message
    expect($bladeContent)->toContain('<h2 class="dso-modal-title">Restore SK Official?</h2>')
        ->and($bladeContent)->toContain('Are you sure you want to restore this SK Official account?');

    // Actions dropdown menu has exactly View and Restore options via ArchiveRowActions structure
    expect($bladeContent)->toContain('data-ar-action="view"')
        ->and($bladeContent)->toContain('data-ar-action="restore"')
        ->and($bladeContent)->toContain('ar-row-actions-trigger')
        ->and($bladeContent)->toContain('archive-row-actions.js');
});

it('renders matching 9 td cells in JavaScript renderDsoTable', function () {
    $jsContent = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/assets/js/deleted-sk-officials.js'));

    // Loading & empty states use colspan 9
    expect($jsContent)->toContain('colspan="9"');

    // 3-dots action menu with view and restore via ArchiveRowActions (like Deleted Posts)
    expect($jsContent)->toContain('ArchiveRowActions.renderMenu')
        ->and($jsContent)->toContain('view: true')
        ->and($jsContent)->toContain('restore: true');

    // Success restore toast
    expect($jsContent)->toContain('SK Official restored successfully.');
});
