<?php

namespace App\Modules\AuditLog\Providers;

use App\Modules\AuditLog\Contracts\AuditLogInterface;
use App\Modules\AuditLog\Services\AuditLogQueryService;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuditLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogInterface::class, AuditLogService::class);
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(AuditLogQueryService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'auditlogs');

        $this->publishes([
            __DIR__.'/../assets' => public_path('modules/audit-log'),
        ], 'audit-log-assets');

        $routesFile = is_file(__DIR__.'/../routes/audit_log.php')
            ? __DIR__.'/../routes/audit_log.php'
            : __DIR__.'/../Routes/audit_log.php';

        Route::middleware('web')->group($routesFile);
    }
}
