<?php

declare(strict_types=1);

use Heyosseus\Filum\Contracts\PresenceStore;
use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Livewire\ChatPanel;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Presence\Heartbeat;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->actingAs($this->nino);
});

it('lists colleagues but never yourself', function (): void {
    Livewire::test(ChatPanel::class)
        ->assertSee('Giorgi')
        ->assertDontSee('Nino');
});

it('invites you to choose someone before a conversation is open', function (): void {
    Livewire::test(ChatPanel::class)
        ->assertSee(__('filum::filum.conversation.none_selected'));
});

it('opens a conversation when a colleague is chosen', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectUser', (string) $this->giorgi->id)
        ->assertSet('selected', (string) $this->giorgi->id)
        ->assertSee(__('filum::filum.conversation.empty'));

    expect(Conversation::query()->count())->toBe(1);
});

it('does not create a conversation merely by listing colleagues', function (): void {
    Livewire::test(ChatPanel::class)->assertOk();

    expect(Conversation::query()->count())->toBe(0);
});

it('sends a message and clears the composer', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectUser', (string) $this->giorgi->id)
        ->set('body', 'გამარჯობა')
        ->call('send')
        ->assertSet('body', '')
        ->assertSee('გამარჯობა');

    expect(Message::query()->where('body', 'გამარჯობა')->exists())->toBeTrue();
});

it('shows an error instead of throwing when the composer is empty', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectUser', (string) $this->giorgi->id)
        ->set('body', '   ')
        ->call('send')
        ->assertHasErrors('body');

    expect(Message::query()->count())->toBe(0);
});

it('shows an error instead of throwing when sending too fast', function (): void {
    config()->set('filum.messages.rate_limit', 1);

    Livewire::test(ChatPanel::class)
        ->call('selectUser', (string) $this->giorgi->id)
        ->set('body', 'one')
        ->call('send')
        ->set('body', 'two')
        ->call('send')
        ->assertHasErrors('body');
});

it('sends nothing when nobody is selected', function (): void {
    Livewire::test(ChatPanel::class)
        ->set('body', 'into the void')
        ->call('send');

    expect(Message::query()->count())->toBe(0);
});

it('filters the sidebar by search', function (): void {
    $this->user('Dato');

    Livewire::test(ChatPanel::class)
        ->set('search', 'dat')
        ->assertSee('Dato')
        ->assertDontSee('Giorgi');
});

it('says so when a search matches nobody', function (): void {
    Livewire::test(ChatPanel::class)
        ->set('search', 'zzzz')
        ->assertSee(__('filum::filum.sidebar.none_found'));
});

it('marks a conversation read when it is opened', function (): void {
    $conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
    app(Messages::class)->send($conversation, $this->giorgi, 'unread');

    expect(app(Messages::class)->unreadIn($conversation, $this->nino))->toBe(1);

    Livewire::test(ChatPanel::class)->call('selectUser', (string) $this->giorgi->id);

    expect(app(Messages::class)->unreadIn($conversation, $this->nino))->toBe(0);
});

it('shows an unread badge for a colleague who wrote first', function (): void {
    $conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
    app(Messages::class)->send($conversation, $this->giorgi, 'knock knock');

    Livewire::test(ChatPanel::class)->assertSeeHtml('filum-count');
});

it('groups the board by who is here and who is away', function (): void {
    app(Heartbeat::class)->beat($this->giorgi);

    Livewire::test(ChatPanel::class)
        ->assertSeeHtml('filum-avatar-live')
        ->assertSeeInOrder([__('filum::filum.sidebar.here'), 'Giorgi']);
});

it('leaves a conversation and clears what was being written', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectUser', (string) $this->giorgi->id)
        ->set('body', 'half a thought')
        ->call('deselect')
        ->assertSet('selected', null)
        ->assertSet('body', '')
        ->assertSet('oldest', null);
});

it('records presence on every tick', function (): void {
    Livewire::test(ChatPanel::class)->call('tick');

    expect(app(PresenceStore::class)->active())->toBe([$this->nino->id]);
});

it('keeps an open conversation read as new messages arrive on a tick', function (): void {
    $conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);

    $panel = Livewire::test(ChatPanel::class)->call('selectUser', (string) $this->giorgi->id);

    app(Messages::class)->send($conversation, $this->giorgi, 'while you were looking');

    expect(app(Messages::class)->unreadIn($conversation, $this->nino))->toBe(1);

    // The thread is on screen, so the tick that fetches it also marks it read.
    $panel->call('tick');

    expect(app(Messages::class)->unreadIn($conversation, $this->nino))->toBe(0);
});

it('walks backwards through a long thread', function (): void {
    config()->set('filum.messages.rate_limit', 0);
    config()->set('filum.messages.per_page', 5);

    $conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);

    foreach (range(1, 12) as $i) {
        app(Messages::class)->send($conversation, $this->giorgi, "message {$i}");
    }

    Livewire::test(ChatPanel::class)
        ->call('selectUser', (string) $this->giorgi->id)
        ->assertSee('message 12')
        ->assertDontSee('message 3')
        ->call('loadOlder')
        ->assertSee('message 3');
});

it('opens and closes in overlay mode', function (): void {
    Livewire::test(ChatPanel::class, ['mode' => 'overlay'])
        ->assertSet('mode', 'overlay')
        ->assertSet('open', false)
        ->assertDontSee(__('filum::filum.sidebar.heading'))
        ->call('toggle')
        ->assertSet('open', true)
        ->assertSee(__('filum::filum.sidebar.heading'));
});

it('falls back to page mode when handed nonsense', function (): void {
    Livewire::test(ChatPanel::class, ['mode' => 'sideways'])
        ->assertSet('mode', 'page');
});

it('shows nothing to an unauthorised viewer', function (): void {
    config()->set('filum.enabled', false);

    Livewire::test(ChatPanel::class)->assertDontSee('Giorgi');
});
