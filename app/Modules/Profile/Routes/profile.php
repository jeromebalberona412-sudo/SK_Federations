<?php

use App\Modules\Profile\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/profile/{type}/{file}', function ($type, $file) {
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

$profileMiddleware = ['auth', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'];

Route::middleware($profileMiddleware)->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');

    Route::get('/change-email', [ProfileController::class, 'showChangeEmail'])->name('change-email');
    Route::post('/change-email', [ProfileController::class, 'requestChangeEmail'])->name('change-email.request');
    Route::get('/change-email/verify', [ProfileController::class, 'showChangeEmailVerify'])->name('change-email.verify');
    Route::post('/change-email/resend', [ProfileController::class, 'resendChangeEmail'])->name('change-email.resend');
    Route::post('/change-email/cancel', [ProfileController::class, 'cancelChangeEmail'])->name('change-email.cancel');
});
