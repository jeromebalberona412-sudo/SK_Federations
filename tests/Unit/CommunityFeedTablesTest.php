<?php

use App\Modules\BarangayMonitoring\Services\BarangayMonitoringService;
use App\Modules\Shared\Models\Announcement;
use App\Modules\Shared\Models\AnnouncementComment;
use App\Modules\Shared\Models\AnnouncementCommentReaction;
use App\Modules\Shared\Models\AnnouncementImage;
use App\Modules\Shared\Models\AnnouncementReaction;

test('community feed models use the renamed community_feeds tables', function () {
    expect((new Announcement)->getTable())->toBe('community_feeds')
        ->and((new AnnouncementComment)->getTable())->toBe('community_feed_comments')
        ->and((new AnnouncementReaction)->getTable())->toBe('community_feed_reactions')
        ->and((new AnnouncementImage)->getTable())->toBe('community_feed_images')
        ->and((new AnnouncementCommentReaction)->getTable())->toBe('community_feed_comment_reactions');
});

test('federation reaction types match the community feed contract', function () {
    expect(AnnouncementReaction::TYPES)->toBe(['like', 'love', 'haha', 'wow', 'sad', 'angry']);
});

test('community feed reaction sound file is published', function () {
    expect(file_exists(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'sounds'.DIRECTORY_SEPARATOR.'reactions_ux.mp3'))->toBeTrue();
});

test('santa cruz barangay map includes all 26 barangays', function () {
    $map = (new BarangayMonitoringService)->slugToNameMap();

    expect($map)->toHaveCount(26)
        ->and($map)->toHaveKey('bubukal')
        ->and($map['bubukal'])->toBe('Bubukal');
});
