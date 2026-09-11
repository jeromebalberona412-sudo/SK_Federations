<?php

use App\Services\ProhibitedWordsService;

it('does not throw undefined alnum when checking community feed text', function () {
    $service = app(ProhibitedWordsService::class);

    expect($service->contains('SK Federation community update for Santa Cruz'))->toBeFalse()
        ->and($service->matches('hello world'))->toBe([]);
});

it('still flags numeric prohibited tokens such as 8080', function () {
    $service = app(ProhibitedWordsService::class);

    expect($service->contains('code 8080 please'))->toBeTrue();
});
