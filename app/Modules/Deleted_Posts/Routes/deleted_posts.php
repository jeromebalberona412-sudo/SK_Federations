<?php

use App\Modules\Deleted_Posts\Controllers\DeletedPostsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'session.timeout', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        Route::get('/archived/deleted-posts', [DeletedPostsController::class, 'index'])
            ->name('archived.deleted-posts');

        Route::get('/deleted-posts', [DeletedPostsController::class, 'index'])
            ->name('deleted-posts');

        Route::get('/archived/deleted-posts/data', [DeletedPostsController::class, 'data'])
            ->name('archived.deleted-posts.data');

        Route::get('/deleted-posts/data', [DeletedPostsController::class, 'data'])
            ->name('deleted-posts.data');

        Route::post('/archived/deleted-posts/{id}/restore', [DeletedPostsController::class, 'restore'])
            ->whereNumber('id')
            ->name('archived.deleted-posts.restore');

        Route::post('/deleted-posts/{id}/restore', [DeletedPostsController::class, 'restore'])
            ->whereNumber('id')
            ->name('deleted-posts.restore');

        Route::delete('/archived/deleted-posts/{id}', [DeletedPostsController::class, 'destroy'])
            ->whereNumber('id')
            ->name('archived.deleted-posts.destroy');

        Route::delete('/deleted-posts/{id}', [DeletedPostsController::class, 'destroy'])
            ->whereNumber('id')
            ->name('deleted-posts.destroy');
    });
