<?php

use App\Modules\Profile\Services\EmailChangeService;
use App\Modules\Profile\Services\PasswordChangeService;
use Tests\TestCase;

uses(TestCase::class);

it('tracks a recently confirmed password change in cache', function () {
    $service = app(PasswordChangeService::class);

    $service->markRecentlyConfirmed(99);

    expect($service->wasRecentlyConfirmed(99))->toBeTrue();

    $service->forgetRecentlyConfirmed(99);

    expect($service->wasRecentlyConfirmed(99))->toBeFalse();
});

it('tracks a recently completed email change in cache', function () {
    $service = app(EmailChangeService::class);

    $service->markRecentlyCompleted(99);

    expect($service->wasRecentlyCompleted(99))->toBeTrue();

    $service->forgetRecentlyCompleted(99);

    expect($service->wasRecentlyCompleted(99))->toBeFalse();
});
