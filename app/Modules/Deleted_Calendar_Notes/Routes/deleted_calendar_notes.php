<?php

use App\Modules\Deleted_Calendar_Notes\Controllers\DeletedCalendarNotesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'session.timeout', 'verified', 'single.session', 'sk_fed.access', 'trusted.device', 'prevent.back'])
    ->group(function () {
        Route::get('/deleted-calendar-notes', [DeletedCalendarNotesController::class, 'index'])
            ->name('deleted-calendar-notes');

        Route::get('/deleted-calendar-notes/data', [DeletedCalendarNotesController::class, 'data'])
            ->name('deleted-calendar-notes.data');

        Route::post('/deleted-calendar-notes/{id}/restore', [DeletedCalendarNotesController::class, 'restore'])
            ->whereNumber('id')
            ->name('deleted-calendar-notes.restore');

        Route::delete('/deleted-calendar-notes/{id}', [DeletedCalendarNotesController::class, 'destroy'])
            ->whereNumber('id')
            ->name('deleted-calendar-notes.destroy');
    });
