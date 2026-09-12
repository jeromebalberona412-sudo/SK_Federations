<?php

use App\Modules\SK_Federation_Records\Controllers\SkFederationRecordsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'session.timeout', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        Route::get('/manage-archive/sk-federation-records', [SkFederationRecordsController::class, 'index'])
            ->name('archived.sk-federation-records');

        Route::get('/sk-federation-records', [SkFederationRecordsController::class, 'index'])
            ->name('sk-federation-records');

        Route::get('/manage-archive/sk-federation-records/data', [SkFederationRecordsController::class, 'data'])
            ->name('archived.sk-federation-records.data');

        Route::get('/sk-federation-records/data', [SkFederationRecordsController::class, 'data'])
            ->name('sk-federation-records.data');

        Route::post('/manage-archive/sk-federation-records/{record}/restore', [SkFederationRecordsController::class, 'restore'])
            ->whereNumber('record')
            ->name('archived.sk-federation-records.restore');

        Route::post('/sk-federation-records/{record}/restore', [SkFederationRecordsController::class, 'restore'])
            ->whereNumber('record')
            ->name('sk-federation-records.restore');
    });
