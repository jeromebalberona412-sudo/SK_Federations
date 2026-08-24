<?php

use App\Modules\BarangayLogos\Controllers\BarangayLogoController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/barangay-logos/{type}/{file}', function (string $type, string $file) {
    $path = __DIR__."/../assets/{$type}/{$file}";

    if (! file_exists($path)) {
        abort(404);
    }

    $mimeTypes = [
        'css' => 'text/css',
        'js'  => 'application/javascript',
    ];

    $extension = pathinfo($file, PATHINFO_EXTENSION);
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

    return response()->file($path, ['Content-Type' => $mimeType]);
})->where('type', 'css|js')->where('file', '.*');

Route::middleware(['auth', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        Route::get('/barangay-logos', [BarangayLogoController::class, 'index'])->name('barangay-logos.index');
        Route::post('/barangay-logos/upload', [BarangayLogoController::class, 'upload'])->name('barangay-logos.upload');
        Route::delete('/barangay-logos/{id}', [BarangayLogoController::class, 'delete'])->name('barangay-logos.delete');
    });
