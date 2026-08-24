<?php

use App\Modules\AuditLog\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/modules/audit-log/{type}/{file}', function (string $type, string $file) {
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
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('auditlogs.index');
        Route::get('/audit-logs/data', [AuditLogController::class, 'data'])->name('auditlogs.data');
        Route::get('/audit-logs/stats', [AuditLogController::class, 'stats'])->name('auditlogs.stats');
        Route::get('/audit-logs/recent', [AuditLogController::class, 'recent'])->name('auditlogs.recent');
        Route::get('/audit-logs/export/csv', [AuditLogController::class, 'exportCsv'])->name('auditlogs.export.csv');
        Route::get('/audit-logs/export/excel', [AuditLogController::class, 'exportExcel'])->name('auditlogs.export.excel');
    });
