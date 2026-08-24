<?php

use App\Services\SkFederationsNotificationService;

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
