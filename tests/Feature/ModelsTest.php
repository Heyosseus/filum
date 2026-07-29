<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Models\Participant;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
});

it('reaches a conversation\'s messages', function (): void {
    app(Messages::class)->send($this->conversation, $this->nino, 'one');
    app(Messages::class)->send($this->conversation, $this->giorgi, 'two');

    expect($this->conversation->messages()->count())->toBe(2)
        ->and($this->conversation->messages->pluck('body')->all())->toBe(['one', 'two']);
});

it('reaches a conversation\'s participants', function (): void {
    expect($this->conversation->participants()->count())->toBe(2);
});

it('walks from a message back to its conversation', function (): void {
    $message = app(Messages::class)->send($this->conversation, $this->nino, 'one');

    expect($message->conversation)->toBeInstanceOf(Conversation::class)
        ->and($message->conversation->id)->toBe($this->conversation->id);
});

it('walks from a participant back to its conversation', function (): void {
    $participant = Participant::query()->where('conversation_id', $this->conversation->id)->firstOrFail();

    expect($participant->conversation)->toBeInstanceOf(Conversation::class)
        ->and($participant->conversation->id)->toBe($this->conversation->id);
});

it('casts what it stores', function (): void {
    $message = app(Messages::class)->send($this->conversation, $this->nino, 'one');

    $participant = Participant::query()
        ->where('conversation_id', $this->conversation->id)
        ->where('user_id', $this->nino->id)
        ->firstOrFail();

    expect($this->conversation->fresh()?->last_message_at)->toBeInstanceOf(Carbon\CarbonInterface::class)
        ->and($participant->last_read_message_id)->toBe($message->id);
});

it('stores a message body verbatim, Georgian included', function (): void {
    $message = app(Messages::class)->send($this->conversation, $this->nino, 'გამარჯობა, როგორ ხარ?');

    expect(Message::query()->find($message->id)?->body)->toBe('გამარჯობა, როგორ ხარ?');
});

it('drops a conversation\'s messages and participants with it', function (): void {
    app(Messages::class)->send($this->conversation, $this->nino, 'one');

    $this->conversation->delete();

    expect(Message::query()->count())->toBe(0)
        ->and(Participant::query()->count())->toBe(0);
});
