<?php

use App\Modules\Archive_Management\Controllers\DeletedPostsArchiveController;
use App\Modules\Archive_Management\Controllers\DeletedArchiveController;
use App\Modules\Archive_Management\Controllers\TermRecordsArchiveController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/archive-management/{type}/{file}', function (string $type, string $file) {
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
        Route::get('/manage-archive/sk-federation-records', function () {
            return view('archive-management::SK_Federation_Records');
        })->name('archived.sk-federation-records');

        Route::get('/manage-archive/sk-federation-records/data', [TermRecordsArchiveController::class, 'federationData'])
            ->name('archived.sk-federation-records.data');

        Route::post('/manage-archive/sk-federation-records/{record}/restore', [TermRecordsArchiveController::class, 'restoreFederationRecord'])
            ->whereNumber('record')
            ->name('archived.sk-federation-records.restore');

        Route::get('/manage-archive/sk-officials-records', function () {
            return view('archive-management::SK_Officials_Records');
        })->name('archived.sk-officials-records');

        Route::get('/manage-archive/sk-officials-records/data', [TermRecordsArchiveController::class, 'officialsData'])
            ->name('archived.sk-officials-records.data');

        Route::post('/manage-archive/sk-officials-records/{record}/restore', [TermRecordsArchiveController::class, 'restoreOfficialRecord'])
            ->whereNumber('record')
            ->name('archived.sk-officials-records.restore');

        Route::get('/archived/deleted-sk-officials', [DeletedArchiveController::class, 'officialsIndex'])
            ->name('archived.deleted-sk-officials');

        Route::get('/archived/deleted-sk-officials/data', [DeletedArchiveController::class, 'officialsData'])
            ->name('archived.deleted-sk-officials.data');

        Route::post('/archived/deleted-sk-officials/{account}/restore', [DeletedArchiveController::class, 'restoreOfficial'])
            ->whereNumber('account')
            ->name('archived.deleted-sk-officials.restore');

        Route::get('/archived/deleted-posts', [DeletedPostsArchiveController::class, 'index'])
            ->name('archived.deleted-posts');

        Route::get('/archived/deleted-posts/data', [DeletedPostsArchiveController::class, 'data'])
            ->name('archived.deleted-posts.data');

        Route::post('/archived/deleted-posts/{id}/restore', [DeletedPostsArchiveController::class, 'restore'])
            ->whereNumber('id')
            ->name('archived.deleted-posts.restore');

        Route::delete('/archived/deleted-posts/{id}', [DeletedPostsArchiveController::class, 'destroy'])
            ->whereNumber('id')
            ->name('archived.deleted-posts.destroy');
    });
