<?php

declare(strict_types=1);

use Heyosseus\Filum\Events\MessageSent;
use Heyosseus\Filum\Events\PresenceChanged;
use Heyosseus\Filum\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts a message on the private conversation channel', function (): void {
    $message = new Message(['conversation_id' => 7, 'sender_id' => 3, 'body' => 'hi']);
    $message->id = 42;

    $event = new MessageSent($message);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-filum.conversation.7')
        ->and($event->broadcastAs())->toBe('filum.message.sent');
});

it('carries an id rather than a body, so subscribers re-read through the authorized path', function (): void {
    $message = new Message(['conversation_id' => 7, 'sender_id' => 3, 'body' => 'secret']);
    $message->id = 42;

    // Parenthesised on purpose: chaining straight off `new` is PHP 8.4 syntax and
    // Filum supports 8.3, where it is a parse error -- which takes the whole suite
    // down rather than one test, since the file cannot even be loaded.
    $payload = (new MessageSent($message))->broadcastWith();

    expect($payload)->toBe(['id' => 42, 'conversation_id' => 7])
        ->and($payload)->not->toHaveKey('body');
});

it('broadcasts presence on the shared presence channel', function (): void {
    $event = new PresenceChanged(5, true);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe('filum.presence')
        ->and($event->broadcastAs())->toBe('filum.presence.changed');
});

it('carries no presence state of its own', function (): void {
    expect((new PresenceChanged(5, true))->broadcastWith())->toBe(['user_id' => 5, 'online' => true])
        ->and((new PresenceChanged('abc', false))->broadcastWith())->toBe(['user_id' => 'abc', 'online' => false]);
});
