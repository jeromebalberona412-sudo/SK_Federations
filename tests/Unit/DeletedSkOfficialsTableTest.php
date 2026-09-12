<?php

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(\Tests\TestCase::class);

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

    // Action column uses 3-dot dropdown menu containing View and Restore actions
    expect($bladeContent)->toContain('ar-row-actions-menu')
        ->and($bladeContent)->toContain('ar-row-actions-trigger')
        ->and($bladeContent)->toContain('ar-row-actions-dropdown')
        ->and($bladeContent)->toContain('ar-row-actions-item')
        ->and($bladeContent)->toContain('data-action="view"')
        ->and($bladeContent)->toContain('data-action="restore"')
        ->and($bladeContent)->toContain('<span>View</span>')
        ->and($bladeContent)->toContain('<span>Restore</span>');
});

it('renders matching 9 td cells in JavaScript renderDsoTable', function () {
    $jsContent = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/assets/js/deleted-sk-officials.js'));

    // Loading & empty states use colspan 9
    expect($jsContent)->toContain('colspan="9"');

    // Action column uses 3-dot dropdown menu containing View and Restore actions
    expect($jsContent)->toContain('ar-row-actions-menu')
        ->and($jsContent)->toContain('ar-row-actions-trigger')
        ->and($jsContent)->toContain('ar-row-actions-dropdown')
        ->and($jsContent)->toContain('ar-row-actions-item')
        ->and($jsContent)->toContain('data-action="view"')
        ->and($jsContent)->toContain('data-action="restore"');

    // Success restore toast
    expect($jsContent)->toContain('SK Official restored successfully.');
});

it('can render the deleted sk officials view without vite or template errors', function () {
    $view = view('Deleted_SK_Officials::deleted-sk-officials', [
        'deletedOfficials' => collect([
            new \Illuminate\Support\Fluent([
                'id' => 1,
                'archive_id' => 1,
                'fullName' => 'DELA CRUZ, JUAN',
                'full_name' => 'DELA CRUZ, JUAN',
                'firstName' => 'JUAN',
                'lastName' => 'DELA CRUZ',
                'middleName' => '',
                'suffix' => '',
                'position' => 'SK Chairperson',
                'barangay' => 'Bagumbayan',
                'municipality' => 'Santa Cruz',
                'term' => '2023 — 2026',
                'termStart' => '2023',
                'termEnd' => '2026',
                'termLabel' => '2023-2026',
                'deletedDate' => 'Sep 12, 2026',
                'deletedTime' => '10:00 AM',
                'deleted_by' => 'Admin User',
            ])
        ]),
        'stats' => ['total' => 1, 'today' => 0, 'month' => 0],
        'filterBarangays' => ['Bagumbayan'],
        'filterPositions' => ['SK Chairperson'],
        'filterYears' => ['2026'],
        'currentFilter' => 'all',
        'currentYear' => 'all',
        'currentBarangay' => '',
        'currentPosition' => '',
        'currentSearch' => '',
        'retentionDays' => 30,
    ])->render();

    expect($view)->toContain('Deleted SK Officials')
        ->and($view)->toContain('DELA CRUZ, JUAN');
});
