<?php

use App\Services\SkFederationsNotificationService;
use Tests\TestCase;

uses(TestCase::class);

test('postLabel prefers title over body', function () {
    $service = new SkFederationsNotificationService;

    expect($service->postLabel('Community Update', 'Long body text that should not be used'))
        ->toBe('Community Update');
});

test('postLabel falls back to stripped body when title is empty', function () {
    $service = new SkFederationsNotificationService;

    expect($service->postLabel(null, '<p>Hello <strong>world</strong></p>'))
        ->toBe('Hello world');
});

test('postLabel returns default when title and body are empty', function () {
    $service = new SkFederationsNotificationService;

    expect($service->postLabel('', '   '))
        ->toBe('your post');
});

test('community feed activity consolidates to one unread notification per post', function () {
    $source = file_get_contents(base_path('app/Services/SkFederationsNotificationService.php'));

    expect($source)->toContain('function notifyCommunityFeedPostActivity(')
        ->and($source)->toContain('function notifyFederationPortalUsersCommunityFeedActivity(')
        ->and($source)->toContain("whereNull('read_at')")
        ->and($source)->toContain("'/community-feed/comments/'")
        ->and($source)->toContain('?open=')
        ->and($source)->toContain("'reactions'")
        ->and($source)->toContain("'comments'")
        ->and($source)->toContain('->delete()')
        ->and($source)->toContain("'created_at' => now()");
});

test('community feed notification deep links open reactions or comments', function () {
    $service = new SkFederationsNotificationService;

    expect($service->communityFeedPostActionUrl(42, 'reactions'))
        ->toBe('/community-feed/comments/42?open=reactions')
        ->and($service->communityFeedPostActionUrl(42, 'comments'))
        ->toBe('/community-feed/comments/42?open=comments')
        ->and($service->communityFeedPostActionUrl(42))
        ->toBe('/community-feed/comments/42');

    $js = file_get_contents(base_path('app/Modules/CommunityFeed/assets/js/community-feed-comment-preview.js'));
    expect($js)->toContain('function applyNotifDeepLinkAfterOpen')
        ->and($js)->toContain("open === 'reactions'")
        ->and($js)->toContain("openViewer('post')");
});

test('layout refreshes a single notification badge in realtime', function () {
    $js = file_get_contents(base_path('app/Modules/Layout/assets/js/layout.js'));
    $css = file_get_contents(base_path('app/Modules/Dashboard/assets/css/dashboard.css'));

    expect($js)->toContain('function refreshFederationsNotifications')
        ->and($js)->toContain("querySelectorAll('#notifBadge')")
        ->and($js)->toContain('renderNotifPopoverItems')
        ->and($js)->toContain('setInterval(function ()')
        ->and($css)->toContain('.notif-badge::before')
        ->and($css)->toContain('.notif-btn .notif-badge ~ .notif-badge');
});
