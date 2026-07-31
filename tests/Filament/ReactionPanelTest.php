<?php

declare(strict_types=1);

use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Livewire\ChatPanel;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Reaction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->actingAs($this->nino, 'panel');

    $this->conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
    $this->message = app(Messages::class)->send($this->conversation, $this->giorgi, 'the van is loaded');
});

it('reacts from the thread and shows the chip', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->call('react', $this->message->id, '👍')
        // The picker renders every configured emoji, so asserting the character
        // alone would pass whether or not a chip exists. The count element is
        // emitted by a chip and nothing else.
        ->assertSeeHtml('filum-reaction-mine')
        ->assertSeeHtml('filum-reaction-count');

    expect(Reaction::query()->count())->toBe(1);
});

it('takes the reaction back on a second tap', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->call('react', $this->message->id, '👍')
        ->call('react', $this->message->id, '👍')
        ->assertDontSeeHtml('filum-reaction-mine');

    expect(Reaction::query()->count())->toBe(0);
});

it('shows a colleague reaction without marking it as yours', function (): void {
    app(Heyosseus\Filum\Reactions\Reactions::class)->toggle($this->message, $this->giorgi, '🎉');

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->assertSeeHtml('filum-reaction-count')
        ->assertDontSeeHtml('filum-reaction-mine');
});

it('offers the picker only while reactions are switched on', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->assertSeeHtml('filum-reaction-add');

    config()->set('filum.reactions.enabled', false);

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->assertDontSeeHtml('filum-reaction-add')
        ->assertDontSeeHtml('filum-reactions');
});

it('gives the row no margin until something has actually been said', function (): void {
    // An untouched message must cost nothing above it; the spacing arrives with
    // the first chip, not with the opener that is invisible anyway.
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->assertDontSeeHtml('filum-reactions-said')
        ->call('react', $this->message->id, '👍')
        ->assertSeeHtml('filum-reactions-said');
});

it('ignores a reaction on a message that is not there', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->call('react', 999999, '👍');

    expect(Reaction::query()->count())->toBe(0);
});

it('ignores an emoji the application does not offer', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->call('react', $this->message->id, '🦄');

    expect(Reaction::query()->count())->toBe(0);
});

it('ignores a reaction on a conversation it is not in', function (): void {
    $theirs = app(Conversations::class)->between($this->giorgi->id, $this->user('Dato')->id);
    $message = app(Messages::class)->send($theirs, $this->giorgi, 'not for you');

    // The id arrives from the browser, so a forged one must write nothing rather
    // than throw at somebody.
    Livewire::test(ChatPanel::class)->call('react', $message->id, '👍');

    expect(Reaction::query()->count())->toBe(0);
});

it('does nothing for an unauthorised viewer', function (): void {
    Heyosseus\Filum\Filum::auth(fn (): bool => false);

    Livewire::test(ChatPanel::class)->call('react', $this->message->id, '👍');

    expect(Reaction::query()->count())->toBe(0);
});

it('picks up a colleague reaction on a tick', function (): void {
    $panel = Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->call('tick');

    app(Heyosseus\Filum\Reactions\Reactions::class)->toggle($this->message, $this->giorgi, '🎉');

    // A reaction moves nothing else in the fingerprint -- no new message, no
    // unread, no presence -- so without its own term the skip-render that
    // protects the composer would hide it until something unrelated changed.
    $panel->call('tick')->assertSeeHtml('filum-reaction-count');
});

it('picks up a colleague removing a reaction on a tick', function (): void {
    $reactions = app(Heyosseus\Filum\Reactions\Reactions::class);
    $reactions->toggle($this->message, $this->giorgi, '🎉');

    $panel = Livewire::test(ChatPanel::class)
        ->call('selectConversation', $this->conversation->id)
        ->call('tick')
        ->assertSeeHtml('filum-reaction-count');

    $reactions->toggle($this->message, $this->giorgi, '🎉');

    // A removal leaves the newest id untouched, which is why the mark carries the
    // count as well.
    $panel->call('tick')->assertDontSeeHtml('filum-reaction-count');
});
