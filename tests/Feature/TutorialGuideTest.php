<?php

use App\Modules\Shared\Models\User;
use App\Modules\Tutorial_Guide\Models\SkFederationTutorial;
use App\Modules\Tutorial_Guide\Services\TutorialGuideService;

test('tutorial model constants and status helpers work correctly', function () {
    $tutorial = new SkFederationTutorial([
        'tutorial_key' => SkFederationTutorial::DEFAULT_TUTORIAL_KEY,
        'current_step' => 1,
        'status' => SkFederationTutorial::STATUS_NOT_STARTED,
    ]);

    expect($tutorial->tutorial_key)->toBe('sk_federations_main_tutorial')
        ->and($tutorial->current_step)->toBe(1)
        ->and($tutorial->shouldAutoStart())->toBeTrue()
        ->and($tutorial->isCompleted())->toBeFalse()
        ->and($tutorial->isSkipped())->toBeFalse()
        ->and($tutorial->isInProgress())->toBeFalse();

    $inProgress = new SkFederationTutorial([
        'status' => SkFederationTutorial::STATUS_IN_PROGRESS,
        'current_step' => 5,
    ]);

    expect($inProgress->isInProgress())->toBeTrue()
        ->and($inProgress->shouldAutoStart())->toBeTrue()
        ->and($inProgress->isCompleted())->toBeFalse();

    $completed = new SkFederationTutorial([
        'status' => SkFederationTutorial::STATUS_COMPLETED,
    ]);

    expect($completed->isCompleted())->toBeTrue()
        ->and($completed->shouldAutoStart())->toBeFalse();

    $skipped = new SkFederationTutorial([
        'status' => SkFederationTutorial::STATUS_SKIPPED,
    ]);

    expect($skipped->isSkipped())->toBeTrue()
        ->and($skipped->shouldAutoStart())->toBeFalse();
});

test('tutorial service formats payload with all required frontend fields', function () {
    $service = new TutorialGuideService();

    $tutorial = new SkFederationTutorial([
        'tutorial_key' => 'sk_federations_main_tutorial',
        'current_step' => 3,
        'status' => SkFederationTutorial::STATUS_IN_PROGRESS,
    ]);

    $payload = $service->formatTutorialPayload($tutorial);

    expect($payload)->toBe([
        'tutorial_key' => 'sk_federations_main_tutorial',
        'current_step' => 3,
        'status' => 'IN_PROGRESS',
        'should_auto_start' => true,
        'is_completed' => false,
        'is_skipped' => false,
    ]);
});

test('tutorial model casts attributes properly', function () {
    $tutorial = new SkFederationTutorial([
        'current_step' => '4',
        'started_at' => '2026-09-13 10:00:00',
        'completed_at' => '2026-09-13 10:15:00',
    ]);

    expect($tutorial->current_step)->toBe(4)
        ->and($tutorial->started_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($tutorial->completed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('tutorial routes are properly registered', function () {
    expect(\Illuminate\Support\Facades\Route::has('api.tutorial-guide.status'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Route::has('api.tutorial-guide.start'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Route::has('api.tutorial-guide.progress'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Route::has('api.tutorial-guide.complete'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Route::has('api.tutorial-guide.skip'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Route::has('api.tutorial-guide.reset'))->toBeTrue();
});

test('sidebar and header have data-tour attributes and tutorial header button', function () {
    $sidebarBlade = file_get_contents(base_path('app/Modules/Layout/Views/sidebar.blade.php'));
    $headerBlade = file_get_contents(base_path('app/Modules/Layout/Views/header.blade.php'));
    $appBlade = file_get_contents(base_path('app/Modules/Layout/Views/app.blade.php'));

    expect($sidebarBlade)->toContain('data-tour="dashboard"')
        ->and($sidebarBlade)->toContain('data-tour="profile"')
        ->and($sidebarBlade)->toContain('data-tour="calendar"')
        ->and($sidebarBlade)->toContain('data-tour="community-feed"')
        ->and($sidebarBlade)->toContain('data-tour="barangay-monitoring"')
        ->and($sidebarBlade)->toContain('data-tour="kabataan-monitoring"')
        ->and($sidebarBlade)->toContain('data-tour="barangay-logos"')
        ->and($sidebarBlade)->toContain('data-tour="audit-logs"')
        ->and($sidebarBlade)->toContain('data-tour="manage-accounts"')
        ->and($sidebarBlade)->toContain('data-tour="archive-management"');

    expect($headerBlade)->toContain('data-tour="sidebar-toggle"')
        ->and($headerBlade)->toContain('data-tour="messages"')
        ->and($headerBlade)->toContain('data-tour="notifications"')
        ->and($headerBlade)->toContain('data-tour="tutorial-guide"')
        ->and($headerBlade)->toContain('data-tour="account-menu"')
        ->and($headerBlade)->toContain('tutorial-header-btn')
        ->and($headerBlade)->toContain('window.startSkFedTutorial');

    expect($appBlade)->toContain('tutorial_guide::tutorial');
});
