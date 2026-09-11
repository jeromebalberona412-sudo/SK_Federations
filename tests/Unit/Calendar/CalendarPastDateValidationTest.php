<?php

use App\Modules\Calendar\Controllers\CalendarController;
use Tests\TestCase;

uses(TestCase::class);
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
    $createMethod->setAccessible(true);
    $modifyMethod->setAccessible(true);

    try {
        $createMethod->invoke($controller, '2026-08-31');
        expect(false)->toBeTrue('Expected ValidationException for past date');
    } catch (ValidationException $e) {
        expect($e)->toBeInstanceOf(ValidationException::class);
    }

    $createMethod->invoke($controller, '2026-09-01');

    try {
        $createMethod->invoke($controller, '2027-01-01');
        expect(false)->toBeTrue('Expected ValidationException for next year');
    } catch (ValidationException $e) {
        expect($e)->toBeInstanceOf(ValidationException::class);
    }

    // Existing notes on past dates may still be edited/deleted
    $modifyMethod->invoke($controller, '2026-08-31');
    $modifyMethod->invoke($controller, '2025-12-31');

    try {
        $modifyMethod->invoke($controller, '2027-01-01');
        expect(false)->toBeTrue('Expected ValidationException for next year modify');
    } catch (ValidationException $e) {
        expect($e)->toBeInstanceOf(ValidationException::class);
    }

    Carbon::setTestNow();
});

it('sanitizes note titles and counts content by visual line length', function () {
    $controller = new CalendarController(
        Mockery::mock(SkFedActivityService::class),
        Mockery::mock(SkFedPageCache::class),
    );

    $titleMethod = new ReflectionMethod($controller, 'assertValidNoteTitle');
    $contentMethod = new ReflectionMethod($controller, 'assertValidNoteContent');
    $lengthMethod = new ReflectionMethod($controller, 'countedNoteLength');
    $titleMethod->setAccessible(true);
    $contentMethod->setAccessible(true);
    $lengthMethod->setAccessible(true);

    expect($titleMethod->invoke($controller, 'Team Meeting'))->toBe('TeamMeeting');

    try {
        $titleMethod->invoke($controller, '   ');
        expect(false)->toBeTrue('Expected ValidationException for empty title');
    } catch (ValidationException $e) {
        expect($e)->toBeInstanceOf(ValidationException::class);
    }

    $shortContent = str_repeat('a', 36);
    expect($contentMethod->invoke($controller, $shortContent))->toBe($shortContent);

    $multiLine = str_repeat('a', 10)."\n".str_repeat('b', 10);
    expect($lengthMethod->invoke($controller, $multiLine, 36))->toBe(46);

    try {
        $contentMethod->invoke($controller, str_repeat('x', 1001));
        expect(false)->toBeTrue('Expected ValidationException for over-limit content');
    } catch (ValidationException $e) {
        expect($e)->toBeInstanceOf(ValidationException::class);
    }
});
