<?php

use App\Modules\Profile\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/change-email/confirm/{id}/{token}', [ProfileController::class, 'confirmChangeEmail'])
    ->middleware('throttle:6,1')
    ->name('change-email.confirm');

Route::get('/change-email/set-password/{id}/{token}', [ProfileController::class, 'showSetPasswordAfterEmailChange'])
    ->middleware('throttle:6,1')
    ->name('change-email.set-password');

Route::post('/change-email/set-password/{id}/{token}', [ProfileController::class, 'updateSetPasswordAfterEmailChange'])
    ->middleware('throttle:6,1')
    ->name('change-email.set-password.update');

Route::get('/change-password/confirm/{id}/{token}', [ProfileController::class, 'confirmChangePassword'])
    ->middleware('throttle:6,1')
    ->name('change-password.confirm');

// Note: Module routes are loaded by their respective service providers.