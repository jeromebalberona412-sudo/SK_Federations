<?php

use App\Modules\Rejected_Abyip\Services\RejectedAbyipService;
use Illuminate\Support\Carbon;

it('calculates rejected abyip retention days remaining and tiers', function () {
    $service = app(RejectedAbyipService::class);

    Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));

    expect($service->daysRemaining(null))->toBe(0)
        ->and($service->daysRemaining(Carbon::parse('2026-09-15 12:00:00')))->toBe(30)
        ->and($service->daysRemaining(Carbon::parse('2026-08-20 12:00:00')))->toBe(4)
        ->and($service->daysRemainingTier(30))->toBe('green')
        ->and($service->daysRemainingTier(7))->toBe('orange')
        ->and($service->daysRemainingTier(3))->toBe('red')
        ->and($service->daysRemainingTier(0))->toBe('red');

    Carbon::setTestNow();
});
