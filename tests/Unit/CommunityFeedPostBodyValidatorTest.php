<?php

use App\Modules\CommunityFeed\Services\CommunityFeedPostBodyValidator;
use App\Services\ProhibitedWordsService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

test('post body validator accepts normalized content within limits', function () {
    $validator = new CommunityFeedPostBodyValidator(new ProhibitedWordsService);

    $result = $validator->assertValid("Hello federation\r\nSecond line");

    expect($result)->toBe("Hello federation\nSecond line");
});

test('post body validator rejects empty content', function () {
    $validator = new CommunityFeedPostBodyValidator(new ProhibitedWordsService);

    $validator->assertValid('   ');
})->throws(ValidationException::class);

test('prohibited words service detects common bypass spacing', function () {
    $service = new ProhibitedWordsService;

    expect($service->contains('p u t a'))->toBeTrue();
});
