<?php

use App\Modules\Barangay_ABYIP\Controllers\BarangayAbyipController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/barangay-abyip/{type}/{file}', function (string $type, string $file) {
    $path = __DIR__."/../Assets/{$type}/{$file}";

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

Route::redirect('/barangay-abyip', '/barangay-monitoring')->name('barangay.abyip');

Route::middleware(['auth', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        Route::get('/api/barangay-abyip', [BarangayAbyipController::class, 'list'])->name('api.barangay-abyip.index');
        Route::get('/api/barangay-abyip/{id}/file', [BarangayAbyipController::class, 'file'])->name('api.barangay-abyip.file');
        Route::get('/api/barangay-abyip/{id}', [BarangayAbyipController::class, 'show'])->name('api.barangay-abyip.show');
        Route::post('/api/barangay-abyip/{id}/approve', [BarangayAbyipController::class, 'approve'])->name('api.barangay-abyip.approve');
        Route::post('/api/barangay-abyip/{id}/reject', [BarangayAbyipController::class, 'reject'])->name('api.barangay-abyip.reject');
        Route::post('/api/barangay-abyip/{id}/revoke', [BarangayAbyipController::class, 'revoke'])->name('api.barangay-abyip.revoke');
    });
