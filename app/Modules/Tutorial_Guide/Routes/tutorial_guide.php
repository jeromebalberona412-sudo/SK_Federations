<?php

use App\Modules\Tutorial_Guide\Controllers\TutorialGuideController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'session.timeout', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->prefix('api/tutorial-guide')
    ->name('api.tutorial-guide.')
    ->group(function () {
        Route::get('/status', [TutorialGuideController::class, 'status'])->name('status');
        Route::post('/start', [TutorialGuideController::class, 'start'])->name('start');
        Route::post('/progress', [TutorialGuideController::class, 'progress'])->name('progress');
        Route::post('/complete', [TutorialGuideController::class, 'complete'])->name('complete');
        Route::post('/skip', [TutorialGuideController::class, 'skip'])->name('skip');
        Route::post('/reset', [TutorialGuideController::class, 'reset'])->name('reset');
    });
