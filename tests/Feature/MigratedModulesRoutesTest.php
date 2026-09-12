<?php

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

it('registers accounts routes in sk federation', function () {
    expect(Route::has('accounts.federation.index'))->toBeTrue();
    expect(Route::has('accounts.officials.index'))->toBeTrue();
    expect(Route::has('accounts.officials.data'))->toBeTrue();
    expect(Route::has('accounts.store'))->toBeTrue();
});

it('registers audit log routes in sk federation', function () {
    expect(Route::has('auditlogs.index'))->toBeTrue();
    expect(Route::has('auditlogs.data'))->toBeTrue();
    expect(Route::has('auditlogs.export.csv'))->toBeTrue();
});

it('registers dashboard feed route in sk federation', function () {
    expect(Route::has('dashboard.feed'))->toBeTrue();
    expect(Route::has('dashboard.recent-activities'))->toBeTrue();
    expect(Route::has('dashboard.stats'))->toBeTrue();
});

it('registers calendar routes in sk federation', function () {
    expect(Route::has('calendar'))->toBeTrue();
    expect(Route::has('api.calendar.notes.index'))->toBeTrue();
    expect(Route::has('api.calendar.notes.store'))->toBeTrue();
    expect(Route::has('api.calendar.notes.destroy'))->toBeTrue();
});

it('registers deleted calendar notes routes in sk federation', function () {
    expect(Route::has('deleted-calendar-notes'))->toBeTrue();
    expect(Route::has('deleted-calendar-notes.data'))->toBeTrue();
    expect(Route::has('deleted-calendar-notes.restore'))->toBeTrue();
    expect(Route::has('deleted-calendar-notes.destroy'))->toBeTrue();
});

it('registers community feed reaction routes in sk federation', function () {
    expect(Route::has('api.community-feed.react'))->toBeTrue();
    expect(Route::has('api.community-feed.comment-react'))->toBeTrue();
    expect(Route::has('community-feed'))->toBeTrue();
    expect(Route::has('community-feed.comments'))->toBeTrue();
    expect(Route::has('api.community-feed.show'))->toBeTrue();
    expect(Route::has('api.community-feed.comments.update'))->toBeTrue();
    expect(Route::has('api.community-feed.comments.destroy'))->toBeTrue();
    expect(Route::has('api.community-feed.comment-reactions'))->toBeTrue();
});

it('redirects guests away from community feed comment preview', function () {
    get('/community-feed/1/comments')->assertRedirect('/login');
});

it('registers archive management routes in sk federation', function () {
    expect(Route::has('archived.deleted-sk-federation'))->toBeFalse();
    expect(Route::has('archived.deleted-sk-officials'))->toBeTrue();
    expect(Route::has('deleted-sk-officials'))->toBeTrue();
    expect(Route::has('archived.deleted-posts'))->toBeTrue();
    expect(Route::has('deleted-posts'))->toBeTrue();
    expect(Route::has('archived.sk-federation-records'))->toBeTrue();
    expect(Route::has('sk-federation-records'))->toBeTrue();
    expect(Route::has('archived.sk-officials-records'))->toBeTrue();
    expect(Route::has('sk-officials-records'))->toBeTrue();
    expect(Route::has('archive'))->toBeFalse();
});

it('redirects guests away from migrated module pages', function () {
    get('/accounts/federation')->assertRedirect('/login');
    get('/accounts/officials/data')->assertRedirect('/login');
    get('/audit-logs')->assertRedirect('/login');
    get('/archived/deleted-sk-officials')->assertRedirect('/login');
    get('/deleted-calendar-notes')->assertRedirect('/login');
    get('/api/dashboard/feed')->assertRedirect('/login');
    get('/api/dashboard/stats')->assertRedirect('/login');
});
