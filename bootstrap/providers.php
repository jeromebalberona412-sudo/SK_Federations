<?php

use App\Modules\Accounts\Providers\AccountsServiceProvider;
use App\Modules\AuditLog\Providers\AuditLogServiceProvider;
use App\Modules\Authentication\Providers\AuthenticationServiceProvider;
use App\Modules\Barangay_ABYIP\Providers\BarangayAbyipServiceProvider;
use App\Modules\BarangayLogos\Providers\BarangayLogosServiceProvider;
use App\Modules\BarangayMonitoring\Providers\BarangayMonitoringServiceProvider;
use App\Modules\Calendar\Providers\CalendarServiceProvider;
use App\Modules\Communications\Providers\CommunicationsServiceProvider;
use App\Modules\CommunityFeed\Providers\CommunityFeedServiceProvider;
use App\Modules\Dashboard\Providers\DashboardServiceProvider;
use App\Modules\Deleted_Calendar_Notes\Providers\DeletedCalendarNotesServiceProvider;
use App\Modules\Deleted_Posts\Providers\DeletedPostsServiceProvider;
use App\Modules\Deleted_SK_Officials\Providers\DeletedSkOfficialsServiceProvider;
use App\Modules\KabataanMonitoring\Providers\KabataanMonitoringServiceProvider;
use App\Modules\Layout\Providers\LayoutServiceProvider;
use App\Modules\Notifications\Providers\NotificationsServiceProvider;
use App\Modules\Profile\Providers\ProfileServiceProvider;
use App\Modules\Reports\Providers\ReportsServiceProvider;
use App\Modules\SK_Federation_Records\Providers\SkFederationRecordsServiceProvider;
use App\Modules\SK_Officials_Records\Providers\SkOfficialsRecordsServiceProvider;
use App\Modules\Shared\Providers\SharedServiceProvider;
use App\Modules\Turnover\Providers\TurnoverServiceProvider;
use App\Providers\AppServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    SharedServiceProvider::class,
    AuthenticationServiceProvider::class,
    DashboardServiceProvider::class,
    CalendarServiceProvider::class,
    ProfileServiceProvider::class,
    CommunityFeedServiceProvider::class,
    BarangayMonitoringServiceProvider::class,
    BarangayAbyipServiceProvider::class,
    KabataanMonitoringServiceProvider::class,
    ReportsServiceProvider::class,
    BarangayLogosServiceProvider::class,
    AccountsServiceProvider::class,
    AuditLogServiceProvider::class,
    DeletedPostsServiceProvider::class,
    DeletedSkOfficialsServiceProvider::class,
    SkOfficialsRecordsServiceProvider::class,
    SkFederationRecordsServiceProvider::class,
    DeletedCalendarNotesServiceProvider::class,
    LayoutServiceProvider::class,
    NotificationsServiceProvider::class,
    CommunicationsServiceProvider::class,
    TurnoverServiceProvider::class,
];
