<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Participant;

it('creates a conversation between two people', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');

    $conversation = app(Conversations::class)->between($nino->id, $giorgi->id);

    expect(Conversation::query()->count())->toBe(1)
        ->and(Participant::query()->where('conversation_id', $conversation->id)->count())->toBe(2);
});

it('returns the same conversation the second time', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');

    $conversations = app(Conversations::class);

    $first = $conversations->between($nino->id, $giorgi->id);
    $second = $conversations->between($giorgi->id, $nino->id);

    expect($second->id)->toBe($first->id)
        ->and(Conversation::query()->count())->toBe(1);
});

it('knows who takes part', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');
    $outsider = $this->user('Dato');

    $conversation = app(Conversations::class)->between($nino->id, $giorgi->id);

    expect($conversation->includes($nino->id))->toBeTrue()
        ->and($conversation->includes($outsider->id))->toBeFalse();
});

it('finds a participant row and returns null for an outsider', function (): void {
    $nino = $this->user('Nino');
    $giorgi = $this->user('Giorgi');
    $outsider = $this->user('Dato');

    $conversations = app(Conversations::class);
    $conversation = $conversations->between($nino->id, $giorgi->id);

    expect($conversations->participant($conversation, $nino->id))->toBeInstanceOf(Participant::class)
        ->and($conversations->participant($conversation, $outsider->id))->toBeNull();
});
