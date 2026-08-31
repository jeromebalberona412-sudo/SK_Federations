<?php

use App\Modules\Calendar\Controllers\CalendarController;
use App\Services\SkFedActivityService;
use App\Services\SkFedPageCache;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

it('blocks calendar note changes on past dates', function () {
    Carbon::setTestNow('2026-09-01');

    $controller = new CalendarController(
        Mockery::mock(SkFedActivityService::class),
        Mockery::mock(SkFedPageCache::class),
    );

    $method = new ReflectionMethod($controller, 'assertCanModifyDate');

    expect(fn () => $method->invoke($controller, '2026-08-31'))
        ->toThrow(ValidationException::class);

    expect(fn () => $method->invoke($controller, '2026-09-01'))
        ->not->toThrow(ValidationException::class);

    expect(fn () => $method->invoke($controller, '2025-12-31'))
        ->toThrow(ValidationException::class);

    expect(fn () => $method->invoke($controller, '2027-01-01'))
        ->toThrow(ValidationException::class);

    Carbon::setTestNow();
});
