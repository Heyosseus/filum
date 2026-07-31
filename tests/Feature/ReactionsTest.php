<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Exceptions\NotAParticipant;
use Heyosseus\Filum\Exceptions\UnknownEmoji;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Reaction;
use Heyosseus\Filum\Reactions\Reactions;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
    $this->message = app(Messages::class)->send($this->conversation, $this->nino, 'the van is loaded');
    $this->reactions = app(Reactions::class);
});

it('adds a reaction', function (): void {
    $this->reactions->toggle($this->message, $this->giorgi, '👍');

    expect(Reaction::query()->count())->toBe(1)
        ->and(Reaction::query()->first()?->emoji)->toBe('👍');
});

it('takes the reaction back when the same person taps the same emoji again', function (): void {
    $this->reactions->toggle($this->message, $this->giorgi, '👍');
    $this->reactions->toggle($this->message, $this->giorgi, '👍');

    // A toggle rather than a create: tapping twice means they changed their mind,
    // which is the only reading that needs no separate remove control.
    expect(Reaction::query()->count())->toBe(0);
});

it('lets one person add several different emoji', function (): void {
    $this->reactions->toggle($this->message, $this->giorgi, '👍');
    $this->reactions->toggle($this->message, $this->giorgi, '🎉');

    expect(Reaction::query()->count())->toBe(2);
});

it('lets two people add the same emoji', function (): void {
    $this->reactions->toggle($this->message, $this->giorgi, '👍');
    $this->reactions->toggle($this->message, $this->nino, '👍');

    expect(Reaction::query()->count())->toBe(2);
});

it('refuses an emoji outside the configured set', function (): void {
    $this->reactions->toggle($this->message, $this->giorgi, '🦄');
})->throws(UnknownEmoji::class);

it('refuses a reaction from someone outside the conversation', function (): void {
    $this->reactions->toggle($this->message, $this->user('Dato'), '👍');
})->throws(NotAParticipant::class);

it('groups a thread into counts and says which are yours', function (): void {
    $this->reactions->toggle($this->message, $this->giorgi, '👍');
    $this->reactions->toggle($this->message, $this->nino, '👍');
    $this->reactions->toggle($this->message, $this->nino, '🎉');

    $thread = app(Messages::class)->page($this->conversation);
    $grouped = $this->reactions->forThread($thread, $this->giorgi);

    expect($grouped[$this->message->id])->toBe([
        ['emoji' => '👍', 'count' => 2, 'mine' => true],
        ['emoji' => '🎉', 'count' => 1, 'mine' => false],
    ]);
});

it('orders reactions by the configured set rather than by count', function (): void {
    // '👍' is first in config and '✅' last, so a lone thumbs-up must still lead a
    // tick with more votes -- chips that reshuffle under the cursor are worse
    // than chips that are merely unsorted.
    $this->reactions->toggle($this->message, $this->nino, '✅');
    $this->reactions->toggle($this->message, $this->giorgi, '✅');
    $this->reactions->toggle($this->message, $this->nino, '👍');

    $thread = app(Messages::class)->page($this->conversation);
    $grouped = $this->reactions->forThread($thread, $this->nino);

    expect(array_column($grouped[$this->message->id], 'emoji'))->toBe(['👍', '✅']);
});

it('reaches the message it belongs to', function (): void {
    $this->reactions->toggle($this->message, $this->giorgi, '👍');

    expect(Reaction::query()->firstOrFail()->message->id)->toBe($this->message->id);
});

it('reads an empty thread without a query', function (): void {
    expect($this->reactions->forThread(collect(), $this->nino))->toBe([]);
});

it('offers the configured emoji', function (): void {
    config()->set('filum.reactions.emoji', ['🔥', '🥲']);

    expect($this->reactions->emoji())->toBe(['🔥', '🥲']);
});

it('falls back to its own set when the configured one is unusable', function (): void {
    config()->set('filum.reactions.emoji', []);
    expect($this->reactions->emoji())->toContain('👍');

    config()->set('filum.reactions.emoji', 'not an array');
    expect($this->reactions->emoji())->toContain('👍');
});

it('drops non-string entries from a configured set', function (): void {
    config()->set('filum.reactions.emoji', ['👍', 42, '🎉']);

    expect($this->reactions->emoji())->toBe(['👍', '🎉']);
});

it('offers nothing and refuses everything when reactions are switched off', function (): void {
    config()->set('filum.reactions.enabled', false);

    expect($this->reactions->emoji())->toBe([]);

    // Disabled means absent: with no emoji in the set, every one of them is
    // unknown, so the same guard that rejects a typo rejects the whole feature.
    $this->reactions->toggle($this->message, $this->giorgi, '👍');
})->throws(UnknownEmoji::class);
