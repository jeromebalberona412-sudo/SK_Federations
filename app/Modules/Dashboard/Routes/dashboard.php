<?php

use App\Modules\Dashboard\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/dashboard/{type}/{file}', function ($type, $file) {
    $path = __DIR__."/../assets/{$type}/{$file}";

    if (! file_exists($path)) {
        abort(404);
    }

    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'webp' => 'image/webp',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
    ];

    $extension = pathinfo($file, PATHINFO_EXTENSION);
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

    return response()->file($path, ['Content-Type' => $mimeType]);
})->where('type', 'css|js|images')->where('file', '.*');

Route::middleware(['auth', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/api/dashboard/kk-profiling', [DashboardController::class, 'kkProfilingData'])
            ->name('dashboard.kk-profiling-data');
        Route::get('/api/dashboard/recent-activities', [DashboardController::class, 'recentActivities'])
            ->name('dashboard.recent-activities');
    });
