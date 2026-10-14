<?php

use App\Models\KabataanRegistration;
use App\Modules\KabataanMonitoring\Support\KkProfilingReviewStatus;

it('classifies pending profiling and unverified submissions as pending', function () {
    $unprofiled = new KabataanRegistration;
    $unprofiled->forceFill([
        'status' => 'password_set',
        'evaluation_status' => 'Not Profiled',
    ]);

    $unverified = new KabataanRegistration;
    $unverified->forceFill([
        'status' => 'pending_verification',
        'evaluation_status' => null,
    ]);

    expect(KkProfilingReviewStatus::labelFor($unprofiled))->toBe('Pending')
        ->and(KkProfilingReviewStatus::isPending($unprofiled))->toBeTrue()
        ->and(KkProfilingReviewStatus::labelFor($unverified))->toBe('Pending');
});

it('classifies verified profiles as approved and rejected states as rejected', function () {
    $approved = new KabataanRegistration;
    $approved->forceFill([
        'status' => 'active',
        'evaluation_status' => 'active',
    ]);

    $rejected = new KabataanRegistration;
    $rejected->forceFill([
        'status' => 'rejected',
        'evaluation_status' => 'Not Profiled',
    ]);

    $duplicate = new KabataanRegistration;
    $duplicate->forceFill([
        'status' => 'active',
        'evaluation_status' => 'Duplicate',
    ]);

    expect(KkProfilingReviewStatus::labelFor($approved))->toBe('Approved')
        ->and(KkProfilingReviewStatus::labelFor($rejected))->toBe('Rejected')
        ->and(KkProfilingReviewStatus::labelFor($duplicate))->toBe('Rejected')
        ->and(KkProfilingReviewStatus::isPending($rejected))->toBeFalse();
});

it('classifies an archived registration as rejected', function () {
    $archived = new KabataanRegistration;
    $archived->forceFill([
        'status' => 'archived',
        'evaluation_status' => 'active',
    ]);

    expect(KkProfilingReviewStatus::labelFor($archived))->toBe('Rejected');
});
