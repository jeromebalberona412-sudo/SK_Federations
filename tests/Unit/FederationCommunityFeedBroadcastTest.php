<?php

use App\Modules\CommunityFeed\Jobs\NotifyFederationCommunityFeedPostJob;
use Tests\TestCase;

uses(TestCase::class);
use App\Modules\CommunityFeed\Notifications\FederationCommunityFeedPostPublishedNotification;
use App\Modules\CommunityFeed\Services\FederationCommunityFeedBroadcastService;
use App\Modules\Shared\Models\Announcement;
use Illuminate\Support\Facades\Queue;

test('federation community feed notification uses federation subject', function () {
    $post = new Announcement([
        'type' => 'announcement',
        'title' => 'Town hall',
        'body' => 'See you there',
    ]);
    $post->created_at = now();

    $notification = new FederationCommunityFeedPostPublishedNotification(
        $post,
        'Juan Dela Cruz',
        'https://kabataan.test/dashboard/comments/1',
    );

    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toBe('New Community Feed post from SK Federation')
        ->and($mail->actionUrl)->toBe('https://kabataan.test/dashboard/comments/1');
});

test('notify federation community feed post job is queueable', function () {
    Queue::fake();

    NotifyFederationCommunityFeedPostJob::dispatch(42, true);

    Queue::assertPushed(NotifyFederationCommunityFeedPostJob::class, function (NotifyFederationCommunityFeedPostJob $job) {
        return $job->postId === 42 && $job->sendEmail === true;
    });
});

test('announcement model exposes email notification flag', function () {
    $announcement = new Announcement([
        'email_notifications_enabled' => true,
    ]);

    expect($announcement->getFillable())->toContain('email_notifications_enabled')
        ->and($announcement->email_notifications_enabled)->toBeTrue();
});

test('announcement notify targets default by visibility', function () {
    $public = new Announcement(['visibility' => 'public']);
    $federation = new Announcement(['visibility' => 'federation']);
    $custom = new Announcement([
        'visibility' => 'federation',
        'notify_targets' => ['officials', 'kabataan'],
    ]);

    expect($public->notifyTargets())->toBe(['public', 'officials', 'kabataan'])
        ->and($federation->notifyTargets())->toBe(['federation', 'officials', 'kabataan'])
        ->and($custom->shouldNotifyOfficials())->toBeTrue()
        ->and($custom->shouldNotifyKabataan())->toBeTrue()
        ->and($custom->shouldNotifyFederationPortal())->toBeFalse();
});

test('broadcast service class exposes in-app and email methods', function () {
    expect(method_exists(FederationCommunityFeedBroadcastService::class, 'broadcastInApp'))->toBeTrue()
        ->and(method_exists(FederationCommunityFeedBroadcastService::class, 'broadcastEmail'))->toBeTrue();
});
