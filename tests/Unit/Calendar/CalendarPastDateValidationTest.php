<?php

use App\Modules\Calendar\Controllers\CalendarController;
use App\Services\SkFedActivityService;
use App\Services\SkFedPageCache;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

it('blocks creating calendar notes on past dates but allows modifying existing notes', function () {
    Carbon::setTestNow('2026-09-01');

    $controller = new CalendarController(
        Mockery::mock(SkFedActivityService::class),
        Mockery::mock(SkFedPageCache::class),
    );

    $createMethod = new ReflectionMethod($controller, 'assertCanCreateNote');
    $modifyMethod = new ReflectionMethod($controller, 'assertCanModifyExistingNote');

    expect(fn () => $createMethod->invoke($controller, '2026-08-31'))
        ->toThrow(ValidationException::class);

    expect(fn () => $createMethod->invoke($controller, '2026-09-01'))
        ->not->toThrow(ValidationException::class);

    expect(fn () => $createMethod->invoke($controller, '2027-01-01'))
        ->toThrow(ValidationException::class);

    // Existing notes on past dates may still be edited/deleted
    expect(fn () => $modifyMethod->invoke($controller, '2026-08-31'))
        ->not->toThrow(ValidationException::class);

    expect(fn () => $modifyMethod->invoke($controller, '2025-12-31'))
        ->not->toThrow(ValidationException::class);

    expect(fn () => $modifyMethod->invoke($controller, '2027-01-01'))
        ->toThrow(ValidationException::class);

    Carbon::setTestNow();
});
