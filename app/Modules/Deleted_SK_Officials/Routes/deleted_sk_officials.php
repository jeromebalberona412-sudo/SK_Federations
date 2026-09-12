<?php

use App\Modules\Deleted_SK_Officials\Controllers\DeletedSkOfficialsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'session.timeout', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        Route::get('/archived/deleted-sk-officials', [DeletedSkOfficialsController::class, 'officialsIndex'])
            ->name('archived.deleted-sk-officials');

        Route::get('/deleted-sk-officials', [DeletedSkOfficialsController::class, 'officialsIndex'])
            ->name('deleted-sk-officials');

        Route::get('/archived/deleted-sk-officials/data', [DeletedSkOfficialsController::class, 'officialsData'])
            ->name('archived.deleted-sk-officials.data');

        Route::get('/deleted-sk-officials/data', [DeletedSkOfficialsController::class, 'officialsData'])
            ->name('deleted-sk-officials.data');

        Route::get('/archived/deleted-sk-officials/{account}', [DeletedSkOfficialsController::class, 'showOfficial'])
            ->whereNumber('account')
            ->name('archived.deleted-sk-officials.show');

        Route::get('/deleted-sk-officials/{account}', [DeletedSkOfficialsController::class, 'showOfficial'])
            ->whereNumber('account')
            ->name('deleted-sk-officials.show');

        Route::post('/archived/deleted-sk-officials/{account}/restore', [DeletedSkOfficialsController::class, 'restoreOfficial'])
            ->whereNumber('account')
            ->name('archived.deleted-sk-officials.restore');

        Route::post('/deleted-sk-officials/{account}/restore', [DeletedSkOfficialsController::class, 'restoreOfficial'])
            ->whereNumber('account')
            ->name('deleted-sk-officials.restore');
    });
