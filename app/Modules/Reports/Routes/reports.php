<?php

use App\Modules\Reports\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/reports/{type}/{file}', function (string $type, string $file) {
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

Route::middleware(['auth', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        Route::redirect('/reports', '/barangay-monitoring')->name('reports');
        Route::get('/api/reports', [ReportsController::class, 'list'])->name('api.reports.index');
        Route::get('/api/reports/{id}/download', [ReportsController::class, 'download'])->name('api.reports.download');
    });
