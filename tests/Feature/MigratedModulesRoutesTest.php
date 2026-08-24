<?php

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

it('registers accounts routes in sk federation', function () {
    expect(Route::has('accounts.federation.index'))->toBeTrue();
    expect(Route::has('accounts.officials.index'))->toBeTrue();
    expect(Route::has('accounts.store'))->toBeTrue();
});

it('registers audit log routes in sk federation', function () {
    expect(Route::has('auditlogs.index'))->toBeTrue();
    expect(Route::has('auditlogs.data'))->toBeTrue();
    expect(Route::has('auditlogs.export.csv'))->toBeTrue();
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
    expect(Route::has('archived.sk-federation-records'))->toBeTrue();
    expect(Route::has('archived.sk-officials-records'))->toBeTrue();
    expect(Route::has('archive'))->toBeFalse();
});

it('redirects guests away from migrated module pages', function () {
    get('/accounts/federation')->assertRedirect('/login');
    get('/audit-logs')->assertRedirect('/login');
    get('/archived/deleted-sk-officials')->assertRedirect('/login');
});
