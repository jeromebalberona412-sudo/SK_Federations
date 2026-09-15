<?php

use App\Modules\Communications\Models\Conversation;
use App\Modules\Communications\Models\ConversationParticipant;
use App\Modules\Communications\Services\DefaultGroupChatService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

it('defines separate default group types and member caps', function () {
    expect(DefaultGroupChatService::TYPE_SK_OFFICIALS)->toBe('SK_OFFICIALS')
        ->and(DefaultGroupChatService::TYPE_SK_FEDERATIONS)->toBe('SK_FEDERATIONS')
        ->and(DefaultGroupChatService::MAX_SK_OFFICIALS)->toBe(260)
        ->and(DefaultGroupChatService::MAX_SK_FEDERATIONS)->toBe(10)
        ->and(DefaultGroupChatService::DEFAULT_NAME_SK_OFFICIALS)->toBe('SK Officials')
        ->and(DefaultGroupChatService::DEFAULT_NAME_SK_FEDERATIONS)->toBe('SK Federations');
});

it('treats default group conversations as groups without mixing types', function () {
    $officials = new Conversation([
        'conversation_type' => Conversation::TYPE_GROUP,
        'name' => 'SK Officials',
        'default_group_type' => DefaultGroupChatService::TYPE_SK_OFFICIALS,
        'max_members' => 260,
    ]);

    $federations = new Conversation([
        'conversation_type' => Conversation::TYPE_GROUP,
        'name' => 'SK Federations',
        'default_group_type' => DefaultGroupChatService::TYPE_SK_FEDERATIONS,
        'max_members' => 10,
    ]);

    $private = new Conversation([
        'conversation_type' => Conversation::TYPE_PRIVATE,
    ]);

    expect($officials->isGroup())->toBeTrue()
        ->and($officials->isDefaultGroup())->toBeTrue()
        ->and($federations->isGroup())->toBeTrue()
        ->and($federations->isDefaultGroup())->toBeTrue()
        ->and($private->isGroup())->toBeFalse()
        ->and($private->isDefaultGroup())->toBeFalse()
        ->and($officials->default_group_type)->not->toBe($federations->default_group_type);
});

it('registers rename and members routes for group conversations', function () {
    expect(Route::has('api.communications.conversations.rename'))->toBeTrue()
        ->and(Route::has('api.communications.conversations.members'))->toBeTrue();
});

it('keeps participant admin role constants for default GC ownership', function () {
    expect(ConversationParticipant::ROLE_ADMIN)->toBe('admin')
        ->and(ConversationParticipant::ROLE_MEMBER)->toBe('member');

    $admin = new ConversationParticipant(['participant_role' => 'admin']);
    $member = new ConversationParticipant(['participant_role' => 'member']);

    expect($admin->isAdmin())->toBeTrue()
        ->and($member->isAdmin())->toBeFalse();
});

it('wires default group ensure service and call participant limit separately from membership', function () {
    $serviceSource = file_get_contents(app_path('Modules/Communications/Services/DefaultGroupChatService.php'));
    $config = file_get_contents(config_path('communications.php'));
    $callSource = file_get_contents(app_path('Modules/Communications/Services/CallService.php'));

    expect($serviceSource)->toContain("TYPE_SK_OFFICIALS = 'SK_OFFICIALS'")
        ->and($serviceSource)->toContain("TYPE_SK_FEDERATIONS = 'SK_FEDERATIONS'")
        ->and($serviceSource)->toContain('MAX_SK_OFFICIALS = 260')
        ->and($serviceSource)->toContain('MAX_SK_FEDERATIONS = 10')
        ->and($serviceSource)->toContain('This group has reached its maximum of')
        ->and($serviceSource)->toContain('federation_position')
        ->and($serviceSource)->toContain('President')
        ->and($config)->toContain('call_max_participants')
        ->and($callSource)->toContain('receiver_id')
        ->and($callSource)->toContain('Simultaneous calls are limited to');
});
