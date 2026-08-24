<?php

use App\Modules\Accounts\Controllers\AdminAccountController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/accounts/{type}/{file}', function (string $type, string $file) {
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
        Route::get('/manage-account', [AdminAccountController::class, 'indexFederation'])->name('accounts.manage');

        Route::get('/accounts/federation', [AdminAccountController::class, 'indexFederation'])->name('accounts.federation.index');
        Route::get('/accounts/officials', [AdminAccountController::class, 'indexOfficials'])->name('accounts.officials.index');

        Route::get('/accounts/create', [AdminAccountController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [AdminAccountController::class, 'store'])->name('accounts.store');
        Route::post('/accounts/batch', [AdminAccountController::class, 'batchStore'])->name('accounts.batch-store');
        Route::post('/accounts/bulk-deactivate', [AdminAccountController::class, 'bulkDeactivate'])->name('accounts.bulk-deactivate');
        Route::get('/accounts/batch-template/{type}', [AdminAccountController::class, 'downloadBatchTemplate'])
            ->where('type', 'officials|federation')
            ->name('accounts.batch-template');

        Route::put('/accounts/{user}', [AdminAccountController::class, 'update'])->name('accounts.update');
        Route::put('/accounts/{user}/federation-position', [AdminAccountController::class, 'updateFederationPosition'])->name('accounts.federation-position.update');

        Route::post('/accounts/{user}/deactivate', [AdminAccountController::class, 'deactivate'])->name('accounts.deactivate');
        Route::post('/accounts/{user}/reset-password', [AdminAccountController::class, 'resetPassword'])->name('accounts.reset-password');
        Route::post('/accounts/{officialProfile}/extend-term', [AdminAccountController::class, 'extendTerm'])->name('accounts.extend-term');
    });
