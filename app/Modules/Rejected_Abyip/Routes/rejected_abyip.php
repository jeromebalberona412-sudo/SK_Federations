<?php

use App\Modules\Rejected_Abyip\Controllers\RejectedAbyipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'session.timeout', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        Route::get('/archived/rejected-abyip', [RejectedAbyipController::class, 'index'])
            ->name('archived.rejected-abyip');

        Route::get('/archived/rejected-abyip/data', [RejectedAbyipController::class, 'data'])
            ->name('archived.rejected-abyip.data');

        Route::get('/archived/rejected-abyip/{id}', [RejectedAbyipController::class, 'show'])
            ->whereNumber('id')
            ->name('archived.rejected-abyip.show');

        Route::post('/archived/rejected-abyip/{id}/restore', [RejectedAbyipController::class, 'restore'])
            ->whereNumber('id')
            ->name('archived.rejected-abyip.restore');

        Route::delete('/archived/rejected-abyip/{id}', [RejectedAbyipController::class, 'destroy'])
            ->whereNumber('id')
            ->name('archived.rejected-abyip.destroy');
    });
