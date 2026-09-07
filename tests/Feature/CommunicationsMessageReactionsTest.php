<?php

use App\Modules\Communications\Models\Message;
use App\Modules\Communications\Models\MessageReaction;
use App\Modules\Communications\Services\MessageService;
use App\Modules\Shared\Models\User;

it('includes reactor names when serializing message reactions', function () {
    config(['communications.portal_user_type' => 'sk_fed']);

    $viewer = new User;
    $viewer->setRawAttributes([
        'id' => 10,
        'name' => 'Cara Federation',
        'role' => 'sk_fed',
    ]);

    $peer = new User;
    $peer->setRawAttributes([
        'id' => 20,
        'name' => 'Diego Official',
        'role' => 'sk_official',
    ]);

    $mine = new MessageReaction([
        'user_id' => 10,
        'user_type' => 'sk_fed',
        'emoji' => '👍',
    ]);
    $mine->setRelation('user', $viewer);

    $theirs = new MessageReaction([
        'user_id' => 20,
        'user_type' => 'sk_official',
        'emoji' => '👍',
    ]);
    $theirs->setRelation('user', $peer);

    $message = new Message;
    $message->setRelation('reactions', collect([$mine, $theirs]));

    $result = app(MessageService::class)->serializeReactions($message, $viewer);

    expect($result)->toHaveCount(1)
        ->and($result[0]['emoji'])->toBe('👍')
        ->and($result[0]['count'])->toBe(2)
        ->and($result[0]['mine'])->toBeTrue()
        ->and($result[0]['users'])->toHaveCount(2)
        ->and(collect($result[0]['users'])->pluck('name')->all())->toContain('Cara Federation', 'Diego Official')
        ->and(collect($result[0]['users'])->firstWhere('mine', true)['name'])->toBe('Cara Federation');
});
