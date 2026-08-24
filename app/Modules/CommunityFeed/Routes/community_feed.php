<?php

use App\Modules\CommunityFeed\Controllers\CommunityFeedController;
use App\Modules\CommunityFeed\Controllers\CommunityFeedPostController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/community-feed/{type}/{file}', function ($type, $file) {
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

Route::middleware(['auth', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        // Views
        Route::get('/community-feed', [CommunityFeedController::class, 'index'])->name('community-feed');
        Route::get('/community-feed/comments/{id}', [CommunityFeedController::class, 'comments'])
            ->whereNumber('id')
            ->name('community-feed.comments');
        Route::get('/community-feed/{id}/comments', function (int $id) {
            return redirect('/community-feed/comments/'.$id);
        })->whereNumber('id');
        Route::get('/sk-federation-profile', [CommunityFeedController::class, 'skFedProfile'])->name('sk-fed-profile');
        Route::post('/sk-federation-profile/post', [CommunityFeedController::class, 'createPost'])->name('sk-fed-profile.post');
        Route::get('/barangay-profile/{slug}', [CommunityFeedController::class, 'barangayProfile'])->name('skfed.barangay-profile');
        Route::get('/barangay-profile/{slug}/comments/{id}', [CommunityFeedController::class, 'barangayComments'])
            ->whereNumber('id')
            ->name('skfed.barangay-profile.comments');

        // Federation-wide post API
        Route::prefix('api/community-feed')->group(function () {
            Route::get('/', [CommunityFeedPostController::class, 'feed'])->name('api.community-feed.feed');
            Route::post('/', [CommunityFeedPostController::class, 'store'])->name('api.community-feed.store');
            Route::post('/upload-image', [CommunityFeedPostController::class, 'uploadImage'])->name('api.community-feed.upload-image');
            Route::get('/{id}', [CommunityFeedPostController::class, 'show'])->whereNumber('id')->name('api.community-feed.show');
            Route::put('/{id}', [CommunityFeedPostController::class, 'update'])->whereNumber('id')->name('api.community-feed.update');
            Route::delete('/{id}', [CommunityFeedPostController::class, 'destroy'])->whereNumber('id')->name('api.community-feed.destroy');
            Route::get('/{id}/likes', [CommunityFeedPostController::class, 'likes'])->whereNumber('id')->name('api.community-feed.likes');
            Route::post('/{id}/react', [CommunityFeedPostController::class, 'react'])->whereNumber('id')->name('api.community-feed.react');
            Route::post('/{id}/comment', [CommunityFeedPostController::class, 'comment'])->whereNumber('id')->name('api.community-feed.comment');
            Route::put('/{id}/comments/{comment}', [CommunityFeedPostController::class, 'updateComment'])
                ->whereNumber('comment')
                ->name('api.community-feed.comments.update');
            Route::delete('/{id}/comments/{comment}', [CommunityFeedPostController::class, 'destroyComment'])
                ->whereNumber('comment')
                ->name('api.community-feed.comments.destroy');
            Route::get('/{id}/comments/{comment}/reactions', [CommunityFeedPostController::class, 'commentReactions'])
                ->whereNumber('comment')
                ->name('api.community-feed.comment-reactions');
            Route::post('/{id}/comments/{comment}/reactions', [CommunityFeedPostController::class, 'commentReact'])
                ->whereNumber('comment')
                ->name('api.community-feed.comment-react');
        });
    });
