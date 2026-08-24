<?php

use App\Modules\Notifications\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/notifications/{type}/{file}', function (string $type, string $file) {
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
    ->group(function (): void {
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');

        Route::get('/api/sk-federations/notifications', [NotificationController::class, 'list'])
            ->name('api.sk-federations.notifications.list');
        Route::post('/api/sk-federations/notifications/{id}/read', [NotificationController::class, 'markRead'])
            ->name('api.sk-federations.notifications.read');
        Route::post('/api/sk-federations/notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->name('api.sk-federations.notifications.read-all');
    });
