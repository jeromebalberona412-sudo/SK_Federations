<?php

use App\Modules\Communications\Controllers\CallController;
use App\Modules\Communications\Controllers\CommunicationsPageController;
use App\Modules\Communications\Controllers\ConversationController;
use App\Modules\Communications\Controllers\MessageController;
use App\Modules\Communications\Controllers\PresenceController;
use App\Modules\Communications\Controllers\UserSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'session.timeout',
    'verified',
    'single.session',
    'sk_fed.access',
    'trusted.device',
    'prevent.back',
])->group(function (): void {
    Route::get('/communications', [CommunicationsPageController::class, 'index'])
        ->name('communications.index');
    Route::get('/communications/calls', [CommunicationsPageController::class, 'callHistory'])
        ->name('communications.calls');

    Route::get('/api/communications/unread-count', [CommunicationsPageController::class, 'unreadCount'])
        ->name('api.communications.unread-count');

    Route::get('/api/communications/conversations', [ConversationController::class, 'index'])
        ->name('api.communications.conversations.index');
    Route::post('/api/communications/conversations', [ConversationController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('api.communications.conversations.store');
    Route::get('/api/communications/conversations/{conversation}', [ConversationController::class, 'show'])
        ->name('api.communications.conversations.show');
    Route::post('/api/communications/conversations/{conversation}/read', [ConversationController::class, 'markRead'])
        ->name('api.communications.conversations.read');

    Route::get('/api/communications/conversations/{conversation}/messages', [MessageController::class, 'index'])
        ->name('api.communications.messages.index');
    Route::post('/api/communications/conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('api.communications.messages.store');
    Route::post('/api/communications/messages/{message}/reactions', [MessageController::class, 'toggleReaction'])
        ->middleware('throttle:60,1')
        ->name('api.communications.messages.react');
    Route::patch('/api/communications/messages/{message}', [MessageController::class, 'update'])
        ->middleware('throttle:60,1')
        ->name('api.communications.messages.update');
    Route::delete('/api/communications/messages/{message}', [MessageController::class, 'destroy'])
        ->middleware('throttle:60,1')
        ->name('api.communications.messages.destroy');
    Route::get('/api/communications/attachments/{attachment}', [\App\Modules\Communications\Controllers\MessageAttachmentController::class, 'download'])
        ->middleware('throttle:60,1')
        ->name('api.communications.attachments.download');

    Route::get('/api/communications/users/search', UserSearchController::class)
        ->middleware('throttle:30,1')
        ->name('api.communications.users.search');

    Route::get('/api/communications/directory', \App\Modules\Communications\Controllers\DirectoryController::class)
        ->middleware('throttle:30,1')
        ->name('api.communications.directory');

    Route::get('/api/communications/calls', [CallController::class, 'index'])
        ->name('api.communications.calls.index');
    Route::post('/api/communications/conversations/{conversation}/calls', [CallController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('api.communications.calls.store');
    Route::post('/api/communications/calls/{call}/status', [CallController::class, 'updateStatus'])
        ->middleware('throttle:30,1')
        ->name('api.communications.calls.status');

    Route::post('/api/communications/presence', [PresenceController::class, 'heartbeat'])
        ->middleware('throttle:20,1')
        ->name('api.communications.presence');
});
