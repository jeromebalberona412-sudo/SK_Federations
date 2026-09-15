<?php

use App\Modules\CommunityFeed\Services\CommunityFeedGoogleDriveFolderSyncService;
use App\Modules\CommunityFeed\Services\GoogleDriveFolderUrlService;
use Tests\TestCase;

uses(TestCase::class);

it('parses Google Drive folder URLs', function (string $url, string $expectedId) {
    $service = app(GoogleDriveFolderUrlService::class);
    $parsed = $service->parse($url);

    expect($parsed)->not->toBeNull()
        ->and($parsed['folder_id'])->toBe($expectedId)
        ->and($parsed['folder_url'])->toBe('https://drive.google.com/drive/folders/'.$expectedId);
})->with([
    'standard' => ['https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOp', '1AbCdEfGhIjKlMnOp'],
    'with query' => ['https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOp?usp=sharing', '1AbCdEfGhIjKlMnOp'],
]);

it('rejects non-folder Drive URLs', function (string $url) {
    $service = app(GoogleDriveFolderUrlService::class);
    expect($service->parse($url))->toBeNull();
})->with([
    'file view' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOp/view',
    'example' => 'https://example.com/drive/folders/1AbCdEfGhIjKlMnOp',
    'javascript' => 'javascript:alert(1)',
    'empty' => '',
]);

it('selects only the oldest configured max video files by createdTime then id', function () {
    $service = app(CommunityFeedGoogleDriveFolderSyncService::class);
    $limit = CommunityFeedGoogleDriveFolderSyncService::maxVideos();

    $files = [];
    for ($i = 1; $i <= $limit + 30; $i++) {
        $files[] = [
            'id' => sprintf('file_%03d', $i),
            'name' => "video{$i}.mp4",
            'mimeType' => 'video/mp4',
            'createdTime' => sprintf('2026-01-%02dT10:00:00.000Z', min(28, $i)),
            'size' => 1000 + $i,
            'webViewLink' => null,
        ];
    }

    $files[] = [
        'id' => 'aaa_same_time',
        'name' => 'tie-a.mp4',
        'mimeType' => 'video/mp4',
        'createdTime' => '2026-01-01T10:00:00.000Z',
        'size' => 1,
        'webViewLink' => null,
    ];
    $files[] = [
        'id' => 'zzz_same_time',
        'name' => 'tie-z.mp4',
        'mimeType' => 'video/mp4',
        'createdTime' => '2026-01-01T10:00:00.000Z',
        'size' => 1,
        'webViewLink' => null,
    ];

    $files[] = [
        'id' => 'img1',
        'name' => 'video_fake.mp4',
        'mimeType' => 'image/jpeg',
        'createdTime' => '2020-01-01T00:00:00.000Z',
        'size' => 1,
        'webViewLink' => null,
    ];

    $selected = $service->selectOldestVideos($files, $limit);

    expect($selected)->toHaveCount($limit)
        ->and($selected[0]['id'])->toBe('aaa_same_time')
        ->and($selected[1]['id'])->toBe('file_001')
        ->and(collect($selected)->every(fn ($row) => str_starts_with($row['mimeType'], 'video/')))->toBeTrue();
});

it('returns fewer than max when folder has fewer valid videos', function () {
    $service = app(CommunityFeedGoogleDriveFolderSyncService::class);
    $files = [
        [
            'id' => 'a',
            'name' => 'a.mp4',
            'mimeType' => 'video/mp4',
            'createdTime' => '2026-02-01T00:00:00.000Z',
            'size' => 1,
            'webViewLink' => null,
        ],
        [
            'id' => 'b',
            'name' => 'b.mp4',
            'mimeType' => 'video/mp4',
            'createdTime' => '2026-02-02T00:00:00.000Z',
            'size' => 1,
            'webViewLink' => null,
        ],
    ];

    $selected = $service->selectOldestVideos($files, 50);

    expect($selected)->toHaveCount(2)
        ->and($selected[0]['id'])->toBe('a')
        ->and($selected[1]['id'])->toBe('b');
});
