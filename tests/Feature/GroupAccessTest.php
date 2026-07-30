<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Models\Participant;

it('treats an existing direct conversation as direct and its participants as joined', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');

    $conversation = app(Conversations::class)->between($nino->id, $giorgi->id);

    expect($conversation->kind)->toBe('direct')
        ->and($conversation->isGroup())->toBeFalse()
        ->and($conversation->name)->toBeNull()
        ->and($conversation->owner_id)->toBeNull()
        ->and($conversation->includes($nino->id))->toBeTrue();

    $participant = app(Conversations::class)->participant($conversation, $nino->id);

    expect($participant?->state)->toBe('joined')
        ->and($participant?->joined_at)->not->toBeNull();
});

it('does not count an invited participant as taking part', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');

    $group = Conversation::query()->create(['kind' => 'group', 'name' => 'Couriers', 'owner_id' => $nino->id]);

    Participant::query()->create([
        'conversation_id' => $group->id,
        'user_id' => $giorgi->id,
        'state' => 'invited',
    ]);

    // The one line the whole feature leans on: includes() backs both channel
    // authorization and the sender check, so a pending invitee is locked out of
    // the socket and the send path by construction.
    expect($group->includes($giorgi->id))->toBeFalse()
        ->and(app(Conversations::class)->participant($group, $giorgi->id))->toBeNull();
});

it('stops counting someone who left', function (): void {
    $nino = $this->user('Nino');

    $group = Conversation::query()->create(['kind' => 'group', 'name' => 'Couriers', 'owner_id' => $nino->id]);

    $participant = Participant::query()->create([
        'conversation_id' => $group->id,
        'user_id' => $nino->id,
        'state' => 'joined',
    ]);

    expect($group->includes($nino->id))->toBeTrue();

    $participant->forceFill(['state' => 'left'])->save();

    expect($group->includes($nino->id))->toBeFalse();
});

it('does not total unread messages for a group the user has only been invited to', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');

    $group = Conversation::query()->create(['kind' => 'group', 'name' => 'Couriers', 'owner_id' => $nino->id]);

    Participant::query()->create([
        'conversation_id' => $group->id,
        'user_id' => $giorgi->id,
        'state' => 'invited',
    ]);

    Message::query()->create([
        'conversation_id' => $group->id,
        'sender_id' => $nino->id,
        'body' => 'Anybody free Tuesday?',
    ]);

    expect(app(Messages::class)->unreadTotal($giorgi))->toBe(0);
});

it('does not total unread messages for a group the user has left', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');

    $group = Conversation::query()->create(['kind' => 'group', 'name' => 'Couriers', 'owner_id' => $nino->id]);

    $participant = Participant::query()->create([
        'conversation_id' => $group->id,
        'user_id' => $giorgi->id,
        'state' => 'joined',
    ]);

    $participant->forceFill(['state' => 'left'])->save();

    Message::query()->create([
        'conversation_id' => $group->id,
        'sender_id' => $nino->id,
        'body' => 'Anybody free Tuesday?',
    ]);

    expect(app(Messages::class)->unreadTotal($giorgi))->toBe(0);
});
