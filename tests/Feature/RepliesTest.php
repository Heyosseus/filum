<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Message;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
    $this->messages = app(Messages::class);
    $this->asked = $this->messages->send($this->conversation, $this->giorgi, 'where is the manifest?');
});

it('answers a particular message', function (): void {
    $answer = $this->messages->send($this->conversation, $this->nino, 'on your desk', $this->asked->id);

    expect($answer->reply_to_id)->toBe($this->asked->id)
        ->and($answer->replyTo?->body)->toBe('where is the manifest?');
});

it('sends without answering anything when no message is named', function (): void {
    expect($this->messages->send($this->conversation, $this->nino, 'just talking')->reply_to_id)->toBeNull();
});

it('ignores an answer aimed at another conversation', function (): void {
    $elsewhere = app(Conversations::class)->between($this->giorgi->id, $this->user('Dato')->id);
    $theirs = $this->messages->send($elsewhere, $this->giorgi, 'not for you');

    $answer = $this->messages->send($this->conversation, $this->nino, 'quoting across', $theirs->id);

    // The id comes from the browser, so pointing it elsewhere would otherwise
    // carry a message's text into a thread not allowed to see it.
    expect($answer->reply_to_id)->toBeNull();
});

it('ignores an answer aimed at nothing', function (): void {
    expect($this->messages->send($this->conversation, $this->nino, 'to the void', 999999)->reply_to_id)->toBeNull();
});

it('keeps an answer when the message it answered is deleted', function (): void {
    $answer = $this->messages->send($this->conversation, $this->nino, 'on your desk', $this->asked->id);

    $this->asked->delete();

    // Null on delete rather than cascade: an answer still says something once the
    // question is gone, and loses only the quote above it.
    expect(Message::query()->find($answer->id))->not->toBeNull()
        ->and(Message::query()->find($answer->id)?->reply_to_id)->toBeNull();
});
