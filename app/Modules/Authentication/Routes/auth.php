<?php

use App\Modules\Authentication\Controllers\AuthController;
use App\Modules\Profile\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/authentication/{type}/{file}', function ($type, $file) {
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

Route::get('/email/verify/wait', [AuthController::class, 'showVerificationWait'])->name('skfed.verification.wait');
Route::get('/email/verify/wait-status', [AuthController::class, 'checkVerificationStatus'])->name('skfed.verification.wait.status');
Route::post('/email/verify/resend', [AuthController::class, 'resendVerification'])->name('skfed.verification.resend');
Route::get('/email/verify/cancel', [AuthController::class, 'cancelVerificationWait'])->name('skfed.verification.cancel');
Route::get('/email/verify-link/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('skfed.verification.verify');
Route::get('/email/verified-success', [AuthController::class, 'showVerificationSuccess'])->name('skfed.verification.success');

// CSRF token refresh endpoint for login page to prevent "Page Expired" errors
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->middleware('web');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/email/verify/notice', [AuthController::class, 'showVerifyNotice'])->name('skfed.verification.notice');

    // Forgot Password Routes
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])
        ->middleware(['turnstile', 'throttle:skfed-password-reset-ip', 'throttle:skfed-password-reset-email'])
        ->name('password.email');
    Route::get('/forgot-password/verify-email', [AuthController::class, 'showForgotPasswordVerifyEmail'])->name('password.verify-email');
    Route::post('/forgot-password/resend', [AuthController::class, 'resendForgotPasswordEmail'])
        ->middleware(['turnstile', 'throttle:skfed-password-reset-ip', 'throttle:skfed-password-reset-email'])
        ->name('password.verify-email.resend');
    Route::get('/set-new-password/{token}', [AuthController::class, 'showResetPassword'])
        ->middleware(['throttle:skfed-password-reset-form'])
        ->name('password.reset');
    Route::post('/set-new-password', [AuthController::class, 'resetPassword'])
        ->middleware(['throttle:skfed-password-reset-ip', 'throttle:skfed-password-reset-email'])
        ->name('password.update');
    Route::get('/reset-password/{token}', function (string $token) {
        return redirect()->route('password.reset', array_merge(['token' => $token], request()->query()));
    });
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware(['throttle:skfed-password-reset-ip', 'throttle:skfed-password-reset-email']);
    Route::get('/password-reset-success', [AuthController::class, 'showPasswordResetSuccess'])->name('password.reset.success');
});

// Compatibility fallback: if a stale client hits GET /logout, avoid 405 and perform logout safely.
Route::middleware('auth')->get('/logout', [AuthController::class, 'logout'])->name('logout.fallback');

Route::middleware(['auth', 'single.session'])->group(function () {
    Route::post('/heartbeat', [AuthController::class, 'heartbeat'])->name('skfed.heartbeat');
});

Route::middleware(['auth', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])->group(function () {
    Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('change-password');
    Route::post('/change-password', [AuthController::class, 'updatePassword'])->name('password.change.update');
    Route::get('/change-password/verify', [ProfileController::class, 'showChangePasswordVerify'])->name('change-password.verify');
    Route::get('/change-password/verify-status', [ProfileController::class, 'checkChangePasswordVerifyStatus'])->name('change-password.verify.status');
    Route::post('/change-password/resend', [ProfileController::class, 'resendChangePassword'])->name('change-password.resend');
    Route::post('/change-password/cancel', [ProfileController::class, 'cancelChangePassword'])->name('change-password.cancel');
});
