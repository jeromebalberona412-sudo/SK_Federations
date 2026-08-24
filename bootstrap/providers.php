<?php

return [
    App\Providers\AppServiceProvider::class,
    Laravel\Fortify\FortifyServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Modules\Shared\Providers\SharedServiceProvider::class,
    App\Modules\Authentication\Providers\AuthenticationServiceProvider::class,
    App\Modules\Dashboard\Providers\DashboardServiceProvider::class,
    App\Modules\Calendar\Providers\CalendarServiceProvider::class,
    App\Modules\Profile\Providers\ProfileServiceProvider::class,
    App\Modules\CommunityFeed\Providers\CommunityFeedServiceProvider::class,
    App\Modules\BarangayMonitoring\Providers\BarangayMonitoringServiceProvider::class,
    App\Modules\Barangay_ABYIP\Providers\BarangayAbyipServiceProvider::class,
    App\Modules\KabataanMonitoring\Providers\KabataanMonitoringServiceProvider::class,
    App\Modules\Reports\Providers\ReportsServiceProvider::class,
    App\Modules\BarangayLogos\Providers\BarangayLogosServiceProvider::class,
    App\Modules\Accounts\Providers\AccountsServiceProvider::class,
    App\Modules\AuditLog\Providers\AuditLogServiceProvider::class,
    App\Modules\Archive_Management\Providers\ArchiveManagementServiceProvider::class,
    App\Modules\Layout\Providers\LayoutServiceProvider::class,
    App\Modules\Notifications\Providers\NotificationsServiceProvider::class,
    App\Modules\Turnover\Providers\TurnoverServiceProvider::class,
];
