<?php

use App\Modules\Turnover\Controllers\TurnoverController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/turnover/{type}/{file}', function (string $type, string $file) {
    $path = __DIR__."/../assets/{$type}/{$file}";

    if (! file_exists($path)) {
        abort(404);
    }

    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
    ];

    $extension = pathinfo($file, PATHINFO_EXTENSION);
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

    return response()->file($path, ['Content-Type' => $mimeType]);
})->where('type', 'css|js')->where('file', '.*');

Route::middleware(['auth', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back', 'turnover.waiting', 'turnover.term.lock'])
    ->group(function () {
        Route::get('/turnover/waiting', [TurnoverController::class, 'waiting'])->name('turnover.waiting');
    });

Route::middleware(['auth', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back', 'turnover.waiting', 'turnover.term.lock', 'turnover.leadership'])
    ->group(function () {
        Route::get('/turnover', fn () => redirect()->route('dashboard'))->name('turnover.index');
        Route::get('/turnover/status', [TurnoverController::class, 'status'])->name('turnover.status');
        Route::post('/turnover/start', [TurnoverController::class, 'start'])
            ->middleware('throttle:'.max(1, (int) config('turnover.registration_rate_limit', 10)))
            ->name('turnover.start');
        Route::get('/turnover/batch-template', [TurnoverController::class, 'downloadBatchTemplate'])->name('turnover.batch-template');
        Route::post('/turnover/register', [TurnoverController::class, 'register'])
            ->middleware('throttle:'.max(1, (int) config('turnover.registration_rate_limit', 10)))
            ->name('turnover.register');
        Route::post('/turnover/{turnover}/complete', [TurnoverController::class, 'complete'])
            ->middleware('throttle:5,1')
            ->name('turnover.complete');
    });
