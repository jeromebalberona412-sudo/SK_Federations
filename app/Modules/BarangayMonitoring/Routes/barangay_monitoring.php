<?php

use App\Modules\BarangayMonitoring\Controllers\BarangayMonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/barangay-monitoring/{type}/{file}', function ($type, $file) {
    $path = __DIR__ . "/../assets/{$type}/{$file}";

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
        Route::get('/barangay-monitoring', [BarangayMonitoringController::class, 'index'])->name('barangay-monitoring');
        Route::get('/barangay-monitoring/{barangay}', [BarangayMonitoringController::class, 'show'])->name('barangay-monitoring.show');

        Route::prefix('api/barangay-monitoring')->group(function () {
            Route::get('/abyip-schedules', [BarangayMonitoringController::class, 'scheduleList'])->name('api.barangay-monitoring.schedules');
            Route::post('/abyip-schedules', [BarangayMonitoringController::class, 'scheduleStore'])->name('api.barangay-monitoring.schedules.store');
            Route::put('/abyip-schedules/{id}', [BarangayMonitoringController::class, 'scheduleUpdate'])->name('api.barangay-monitoring.schedules.update');
            Route::post('/abyip-schedules/{id}/extend', [BarangayMonitoringController::class, 'scheduleExtend'])->name('api.barangay-monitoring.schedules.extend');
            Route::post('/abyip-schedules/{id}/cancel', [BarangayMonitoringController::class, 'scheduleCancel'])->name('api.barangay-monitoring.schedules.cancel');
            Route::delete('/abyip-schedules/{id}', [BarangayMonitoringController::class, 'scheduleDestroy'])->name('api.barangay-monitoring.schedules.destroy');
        });
    });
