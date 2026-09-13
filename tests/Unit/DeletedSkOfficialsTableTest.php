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

it('has separate term start/end columns and three-dot archive actions', function () {
    $bladeContent = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/Views/deleted-sk-officials.blade.php'));

    expect($bladeContent)->toContain('Full Name')
        ->and($bladeContent)->toContain('LN, FN, MN, Suffix')
        ->and($bladeContent)->toContain('<th>Position</th>')
        ->and($bladeContent)->toContain('<th>Barangay</th>')
        ->and($bladeContent)->toContain('<th>Municipality</th>')
        ->and($bladeContent)->toContain('<th>Term Start</th>')
        ->and($bladeContent)->toContain('<th>Term End</th>')
        ->and($bladeContent)->not->toContain('<th>Term</th>')
        ->and($bladeContent)->toContain('<th>Date Deleted</th>')
        ->and($bladeContent)->toContain('<th>Time Deleted</th>')
        ->and($bladeContent)->toContain('<th>Deleted By</th>')
        ->and($bladeContent)->toContain('<th class="dso-col-actions">Actions</th>');

    expect($bladeContent)->not->toContain('<th>Email Address</th>')
        ->and($bladeContent)->not->toContain('<th>Auto-Delete In</th>');

    expect($bladeContent)->toContain('colspan="10"');

    expect($bladeContent)->toContain('<h2 class="dso-modal-title">Restore SK Official?</h2>')
        ->and($bladeContent)->toContain('Are you sure you want to restore this SK Official account?')
        ->and($bladeContent)->toContain('id="dsoViewModal"')
        ->and($bladeContent)->toContain('id="dsoRestoreModal"')
        ->and($bladeContent)->toContain('id="dsoClearFiltersBtn"')
        ->and($bladeContent)->toContain('/shared/js/archive-row-actions.js');

    expect($bladeContent)->toContain('ar-row-actions-menu')
        ->and($bladeContent)->toContain('ar-row-actions-trigger')
        ->and($bladeContent)->toContain('data-ar-action="view"')
        ->and($bladeContent)->toContain('data-ar-action="restore"');
});

it('renders matching term start/end cells and archive row actions in JavaScript', function () {
    $jsContent = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/assets/js/deleted-sk-officials.js'));

    expect($jsContent)->toContain('colspan="10"')
        ->and($jsContent)->toContain('resolveDsoTermParts')
        ->and($jsContent)->toContain('ArchiveRowActions')
        ->and($jsContent)->toContain('openDsoViewModal')
        ->and($jsContent)->toContain('openDsoRestoreModal')
        ->and($jsContent)->toContain('closeDsoRestoreModal(true)')
        ->and($jsContent)->toContain('SK Official restored successfully.')
        ->and($jsContent)->toContain('dsoClearFiltersBtn')
        ->and($jsContent)->toContain('DSO_CACHE_PREFIX');
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
                'term' => 'Jan 1, 2023 — Dec 31, 2025',
                'termStart' => 'Jan 1, 2023',
                'termEnd' => 'Dec 31, 2025',
                'termLabel' => '2023-2025',
                'deletedDate' => 'Sep 12, 2026',
                'deletedTime' => '10:00 AM',
                'deleted_by' => 'Admin User',
            ])
        ]),
        'stats' => ['total' => 1, 'today' => 0, 'month' => 0],
        'filterBarangays' => ['Bagumbayan'],
        'filterPositions' => ['SK Chairperson'],
        'filterYears' => [2026],
        'filterTerms' => [],
        'currentFilter' => 'all',
        'currentYear' => 'all',
        'currentBarangay' => '',
        'currentPosition' => '',
        'currentSearch' => '',
        'retentionDays' => 30,
    ]);

    $html = $view->render();

    expect($html)->toContain('Deleted SK Officials')
        ->and($html)->toContain('DELA CRUZ, JUAN')
        ->and($html)->toContain('Term Start')
        ->and($html)->toContain('Term End')
        ->and($html)->toContain('Jan 1, 2023')
        ->and($html)->toContain('Dec 31, 2025')
        ->and($html)->toContain('ar-row-actions-menu')
        ->and($html)->toContain('data-ar-action="view"')
        ->and($html)->toContain('data-ar-action="restore"');
});
