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
    $this->actingAs($this->nino, 'panel');
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
    $panel = Livewire::test(ChatPanel::class)
        ->call('selectUser', (string) $this->giorgi->id)
        ->assertSee(__('filum::filum.conversation.empty'));

    expect(Conversation::query()->count())->toBe(1);

    $panel->assertSet('conversation', Conversation::query()->firstOrFail()->id);
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
        ->assertSet('conversation', null)
        ->assertSet('body', '')
        ->assertSet('from', null);
});

it('polls quickly and listens for nothing when there is no broadcaster', function (): void {
    Livewire::test(ChatPanel::class)
        ->assertSeeHtml('wire:poll.5s="tick"')
        ->assertDontSeeHtml('window.Echo');
});

it('listens over the socket and slows the poll to reconciliation under a broadcaster', function (): void {
    $this->withBroadcaster();

    Livewire::test(ChatPanel::class)
        ->assertSeeHtml('wire:poll.30s="tick"')
        ->assertSeeHtml('filum.presence')
        ->call('selectUser', (string) $this->giorgi->id)
        ->assertSeeHtml('filum.conversation.')
        ->assertSeeHtml('$wire.received()');
});

it('marks an open conversation read when a broadcast arrives', function (): void {
    $conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);

    $panel = Livewire::test(ChatPanel::class)->call('selectUser', (string) $this->giorgi->id);

    app(Messages::class)->send($conversation, $this->giorgi, 'over the wire');

    expect(app(Messages::class)->unreadIn($conversation, $this->nino))->toBe(1);

    $panel->call('received')->assertSee('over the wire');

    expect(app(Messages::class)->unreadIn($conversation, $this->nino))->toBe(0);
});

it('receives harmlessly with nothing open', function (): void {
    Livewire::test(ChatPanel::class)->call('received')->assertSet('conversation', null);
});

it('records presence on every tick', function (): void {
    Livewire::test(ChatPanel::class)->call('tick');

    expect(app(PresenceStore::class)->active())->toBe([$this->nino->id]);
});

it('leaves the page alone on a tick that finds nothing new', function (): void {
    $panel = Livewire::test(ChatPanel::class)
        ->call('selectUser', (string) $this->giorgi->id)
        ->assertSee('Giorgi')
        // The first tick is legitimately a change: it is the one that records the
        // viewer's own presence. Settle that before testing the quiet case.
        ->call('tick');

    // Renamed behind the component's back. A name is not part of the fingerprint,
    // so a tick that re-rendered would show the new one -- which makes the old one
    // still being there proof that no render, and so no DOM morph, happened. That
    // morph is what takes the caret out of the box somebody is typing in.
    $this->giorgi->update(['name' => 'Giorgi Renamed']);

    $panel->call('tick')->assertDontSee('Giorgi Renamed');
});

it('re-renders on a tick that finds a new message', function (): void {
    $conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);

    $panel = Livewire::test(ChatPanel::class)->call('selectUser', (string) $this->giorgi->id);

    app(Messages::class)->send($conversation, $this->giorgi, 'look at this');

    $panel->call('tick')->assertSee('look at this');
});

it('re-renders on a tick that finds a colleague arriving', function (): void {
    $panel = Livewire::test(ChatPanel::class)->call('tick');

    app(Heartbeat::class)->beat($this->giorgi);

    $panel->call('tick')->assertSeeHtml('filum-avatar-live');
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

it('keeps the newest messages on screen when it reaches back for earlier ones', function (): void {
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
        // Both, which is the point: reaching back adds context rather than
        // swapping one window of the conversation for another.
        ->assertSee('message 3')
        ->assertSee('message 12');
});

it('offers earlier messages only while there are earlier messages', function (): void {
    config()->set('filum.messages.rate_limit', 0);
    config()->set('filum.messages.per_page', 5);

    $conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);

    foreach (range(1, 8) as $i) {
        app(Messages::class)->send($conversation, $this->giorgi, "message {$i}");
    }

    $panel = Livewire::test(ChatPanel::class)->call('selectUser', (string) $this->giorgi->id);

    $panel->assertSee(__('filum::filum.conversation.load_older'))
        ->call('loadOlder')
        ->assertSee('message 1')
        ->assertDontSee(__('filum::filum.conversation.load_older'));
});

it('says nothing about earlier messages in an empty conversation', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectUser', (string) $this->giorgi->id)
        ->assertDontSee(__('filum::filum.conversation.load_older'));
});

it('reaches back for nothing when there is no conversation open', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('loadOlder')
        ->assertSet('from', null)
        ->call('selectUser', (string) $this->giorgi->id)
        // Selected, but nothing has been said yet: still nowhere to reach back to.
        ->call('loadOlder')
        ->assertSet('from', null);
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

it('selects a conversation rather than a person', function (): void {
    $panel = Livewire::test(ChatPanel::class)->call('selectUser', (string) $this->giorgi->id);

    $conversation = Conversation::query()->firstOrFail();

    $panel->assertSet('conversation', $conversation->id);
});

it('opens a group it is a member of', function (): void {
    $groups = app(Heyosseus\Filum\Groups\Groups::class);
    $group = $groups->create($this->nino, 'Couriers');
    app(Messages::class)->send($group, $this->nino, 'shift starts at six');

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->assertSet('conversation', $group->id)
        ->assertSee('Couriers')
        ->assertSee('shift starts at six');
});

it('refuses to open a conversation it is not in', function (): void {
    $group = app(Heyosseus\Filum\Groups\Groups::class)->create($this->giorgi, 'Theirs');

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->assertSet('conversation', null);
});

it('refuses to open a conversation that does not exist', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', 987654)
        ->assertSet('conversation', null);
});

it('opens nothing for a colleague who no longer exists', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectUser', '987654')
        ->assertSet('conversation', null);

    expect(Conversation::query()->count())->toBe(0);
});

it('names each speaker in a group thread', function (): void {
    $groups = app(Heyosseus\Filum\Groups\Groups::class);
    $group = $groups->create($this->nino, 'Couriers');
    $groups->invite($group, $this->nino, $this->giorgi->id);
    $groups->accept($group, $this->giorgi);
    app(Messages::class)->send($group, $this->giorgi, 'on my way');

    // Anchored on the group name, which only the thread header carries: without it
    // the sidebar's own "Giorgi" row -- rendered before the thread -- satisfies the
    // assertion on its own and the sender-name map goes unguarded.
    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->assertSeeInOrder(['Couriers', 'Giorgi', 'on my way']);
});
