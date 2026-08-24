<?php

use App\Modules\Calendar\Controllers\CalendarController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/calendar/{type}/{file}', function (string $type, string $file) {
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
        Route::get('/calendar', [CalendarController::class, 'page'])->name('calendar');
        Route::get('/api/calendar/notes', [CalendarController::class, 'index'])->name('api.calendar.notes.index');
        Route::post('/api/calendar/notes', [CalendarController::class, 'store'])->name('api.calendar.notes.store');
        Route::put('/api/calendar/notes/{id}', [CalendarController::class, 'update'])->name('api.calendar.notes.update');
        Route::delete('/api/calendar/notes/{id}', [CalendarController::class, 'destroy'])->name('api.calendar.notes.destroy');
    });
