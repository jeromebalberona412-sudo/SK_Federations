<?php

use App\Modules\SK_Officials_Records\Controllers\SkOfficialsRecordsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'session.timeout', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        Route::get('/manage-archive/sk-officials-records', [SkOfficialsRecordsController::class, 'index'])
            ->name('archived.sk-officials-records');

        Route::get('/sk-officials-records', [SkOfficialsRecordsController::class, 'index'])
            ->name('sk-officials-records');

        Route::get('/manage-archive/sk-officials-records/data', [SkOfficialsRecordsController::class, 'data'])
            ->name('archived.sk-officials-records.data');

        Route::get('/sk-officials-records/data', [SkOfficialsRecordsController::class, 'data'])
            ->name('sk-officials-records.data');

        Route::post('/manage-archive/sk-officials-records/{record}/restore', [SkOfficialsRecordsController::class, 'restore'])
            ->whereNumber('record')
            ->name('archived.sk-officials-records.restore');

        Route::post('/sk-officials-records/{record}/restore', [SkOfficialsRecordsController::class, 'restore'])
            ->whereNumber('record')
            ->name('sk-officials-records.restore');
    });
