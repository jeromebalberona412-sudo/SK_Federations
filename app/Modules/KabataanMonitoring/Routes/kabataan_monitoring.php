<?php

use App\Modules\KabataanMonitoring\Controllers\KabataanMonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/kabataan-monitoring/{type}/{file}', function ($type, $file) {
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
        Route::get('/kabataan-monitoring', [KabataanMonitoringController::class, 'index'])->name('kabataan-monitoring');
        Route::get('/api/kabataan-monitoring', [KabataanMonitoringController::class, 'data'])->name('api.kabataan-monitoring.index');
        Route::get('/api/kabataan-monitoring/{id}/questionnaire', [KabataanMonitoringController::class, 'questionnaire'])->name('api.kabataan-monitoring.questionnaire');
        Route::get('/api/kabataan-monitoring/{id}/edit', [KabataanMonitoringController::class, 'editData'])->whereNumber('id')->name('api.kabataan-monitoring.edit');
        Route::put('/api/kabataan-monitoring/{id}', [KabataanMonitoringController::class, 'update'])->whereNumber('id')->name('api.kabataan-monitoring.update');
        Route::delete('/api/kabataan-monitoring/{id}', [KabataanMonitoringController::class, 'destroy'])->whereNumber('id')->name('api.kabataan-monitoring.destroy');
        Route::post('/kabataan-monitoring/batch-print', [KabataanMonitoringController::class, 'batchPrint'])->name('kabataan-monitoring.batch-print');
        Route::get('/kabataan-monitoring/barangay/{barangay}', [KabataanMonitoringController::class, 'barangayDetail'])->name('kabataan-monitoring.barangay');
        Route::get('/kabataan-monitoring/{kabataan}', [KabataanMonitoringController::class, 'show'])->name('kabataan-monitoring.show');
    });
