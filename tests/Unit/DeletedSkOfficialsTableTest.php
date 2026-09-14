<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Fluent;
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

it('has table columns and three-dot actions that open View/Restore', function () {
    $bladeContent = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/Views/deleted-sk-officials.blade.php'));

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

    expect($bladeContent)->toContain('<h2 class="dso-modal-title">Restore SK Official?</h2>')
        ->and($bladeContent)->toContain('Are you sure you want to restore this SK Official account?')
        ->and($bladeContent)->toContain('id="dsoViewModal"')
        ->and($bladeContent)->toContain('id="dsoRestoreModal"');

    expect($bladeContent)->toContain('ar-row-actions-menu')
        ->and($bladeContent)->toContain('ar-row-actions-trigger')
        ->and($bladeContent)->toContain('dso-actions-trigger')
        ->and($bladeContent)->toContain('data-record-id');
});

it('opens a floating View/Restore menu from the three-dot button in JavaScript', function () {
    $jsContent = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/assets/js/deleted-sk-officials.js'));

    expect($jsContent)->toContain('colspan="9"')
        ->and($jsContent)->toContain('ensureDsoActionsDropdown')
        ->and($jsContent)->toContain('openDsoActionsMenu')
        ->and($jsContent)->toContain('dsoActionsDropdown')
        ->and($jsContent)->toContain('data-dso-action="view"')
        ->and($jsContent)->toContain('data-dso-action="restore"')
        ->and($jsContent)->toContain('openDsoViewModal')
        ->and($jsContent)->toContain('openDsoRestoreModal')
        ->and($jsContent)->toContain('bindDsoTableActions()')
        ->and($jsContent)->toContain('closeAllDsoActionMenus()')
        ->and($jsContent)->toContain('SK Official restored successfully.');
});

it('initializes after module state and reloads filters without dropping newer requests', function () {
    $jsContent = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/assets/js/deleted-sk-officials.js'));
    $initPos = strrpos($jsContent, 'onArchiveReady(initDeletedSkOfficials)');
    $statePos = strpos($jsContent, 'let dsoLoadSeq = 0');
    $earlyInit = strpos($jsContent, 'onArchiveReady(initDeletedSkOfficials)');

    expect($initPos)->not->toBeFalse()
        ->and($statePos)->not->toBeFalse()
        ->and($initPos)->toBeGreaterThan($statePos)
        ->and(substr_count($jsContent, 'onArchiveReady(initDeletedSkOfficials)'))->toBe(1)
        ->and($jsContent)->toContain('if (seq !== dsoLoadSeq) return')
        ->and($jsContent)->toContain("setTimeout(() => {\n            dsoCurrentPage = 1;\n            loadDsoRecords();\n        }, 150)");
});

it('keeps the table visible instead of a loading placeholder', function () {
    $jsContent = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/assets/js/deleted-sk-officials.js'));

    expect($jsContent)->not->toContain('Loading deleted records...')
        ->and($jsContent)->not->toContain('renderDsoLoadingState');
});

it('styles restore actions green and keeps Actions header readable', function () {
    $css = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/assets/css/deleted-sk-officials.css'));
    $blade = file_get_contents(base_path('app/Modules/Deleted_SK_Officials/Views/deleted-sk-officials.blade.php'));

    expect($css)->toContain('.dso-actions-dropdown')
        ->and($css)->toContain('position: fixed')
        ->and($css)->toContain('z-index: 1400')
        ->and($css)->toContain('.dso-actions-dropdown[hidden]')
        ->and($css)->toContain('display: none !important')
        ->and($css)->toContain('.dso-actions-item-restore')
        ->and($css)->toContain('color: #15803d')
        ->and($css)->toContain('.dso-btn-confirm')
        ->and($css)->toContain('background: #16a34a !important')
        ->and($css)->toContain('min-width: 96px')
        ->and($css)->toContain('@media (max-width: 767.98px)')
        ->and($css)->toContain('border-right: 1px solid #d1d5db')
        ->and($css)->toContain('text-align: center !important')
        ->and($css)->toContain('.dso-empty-message')
        ->and($blade)->toContain('dso-btn-restore')
        ->and($blade)->toContain('dso-modal-header--restore')
        ->and($blade)->toContain('dso-empty-message');
});

it('can render the deleted sk officials view without vite or template errors', function () {
    $view = view('Deleted_SK_Officials::deleted-sk-officials', [
        'deletedOfficials' => collect([
            new Fluent([
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
            ]),
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
        ->and($html)->toContain('Term')
        ->and($html)->toContain('Jan 1, 2023 — Dec 31, 2025')
        ->and($html)->toContain('ar-row-actions-menu')
        ->and($html)->toContain('dso-actions-trigger')
        ->and($html)->toContain('data-record-id');
});
