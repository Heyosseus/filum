<?php

declare(strict_types=1);

use Heyosseus\Filum\Groups\Groups;
use Heyosseus\Filum\Livewire\ChatPanel;
use Heyosseus\Filum\Models\Conversation;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->actingAs($this->nino, 'panel');
});

it('creates a group from the board and opens it', function (): void {
    Livewire::test(ChatPanel::class)
        ->set('groupName', 'Couriers')
        ->call('createGroup')
        ->assertSet('groupName', '')
        ->assertSee('Couriers');

    expect(Conversation::query()->where('kind', 'group')->count())->toBe(1);
});

it('shows an error rather than throwing on an unnamed group', function (): void {
    Livewire::test(ChatPanel::class)
        ->set('groupName', '   ')
        ->call('createGroup')
        ->assertHasErrors('groupName');

    expect(Conversation::query()->where('kind', 'group')->exists())->toBeFalse();
});

it('lists an invitation and joins on accepting', function (): void {
    $group = app(Groups::class)->create($this->giorgi, 'Couriers');
    app(Groups::class)->invite($group, $this->giorgi, $this->nino->id);

    Livewire::test(ChatPanel::class)
        ->assertSee(__('filum::filum.sidebar.invitations'))
        ->assertSee('Couriers')
        ->call('acceptInvitation', $group->id)
        ->assertDontSee(__('filum::filum.sidebar.invitations'));

    expect($group->fresh()?->includes($this->nino->id))->toBeTrue();
});

it('drops an invitation on declining', function (): void {
    $group = app(Groups::class)->create($this->giorgi, 'Couriers');
    app(Groups::class)->invite($group, $this->giorgi, $this->nino->id);

    Livewire::test(ChatPanel::class)
        ->call('declineInvitation', $group->id)
        ->assertDontSee(__('filum::filum.sidebar.invitations'));

    expect($group->fresh()?->includes($this->nino->id))->toBeFalse();
});

it('hides the invitations section when there are none', function (): void {
    Livewire::test(ChatPanel::class)->assertDontSee(__('filum::filum.sidebar.invitations'));
});

it('invites a colleague from the group header', function (): void {
    $group = app(Groups::class)->create($this->nino, 'Couriers');

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->call('inviteToGroup', (string) $this->giorgi->id);

    expect($group->participants()->where('user_id', $this->giorgi->id)->value('state'))->toBe('invited');
});

it('leaves a group and closes the thread', function (): void {
    $groups = app(Groups::class);
    $group = $groups->create($this->giorgi, 'Couriers');
    $groups->invite($group, $this->giorgi, $this->nino->id);
    $groups->accept($group, $this->nino);

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->call('leaveGroup')
        ->assertSet('conversation', null);

    expect($group->fresh()?->includes($this->nino->id))->toBeFalse();
});

it('deletes a group it owns and closes the thread', function (): void {
    $group = app(Groups::class)->create($this->nino, 'Couriers');
    $id = $group->id;

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $id)
        ->call('deleteGroup')
        ->assertSet('conversation', null);

    expect(Conversation::query()->find($id))->toBeNull();
});

it('refuses to delete a group it does not own, without throwing', function (): void {
    $groups = app(Groups::class);
    $group = $groups->create($this->giorgi, 'Couriers');
    $groups->invite($group, $this->giorgi, $this->nino->id);
    $groups->accept($group, $this->nino);

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->call('deleteGroup')
        ->assertHasErrors('groupName');

    expect(Conversation::query()->find($group->id))->not->toBeNull();
});

it('creates nothing for a viewer it will not admit', function (): void {
    config()->set('filum.enabled', false);

    Livewire::test(ChatPanel::class)
        ->set('groupName', 'Couriers')
        ->call('createGroup')
        ->assertHasNoErrors('groupName');

    expect(Conversation::query()->where('kind', 'group')->exists())->toBeFalse();
});

it('shows no groups section and creates nothing when groups are switched off', function (): void {
    config()->set('filum.groups.enabled', false);

    Livewire::test(ChatPanel::class)
        ->assertDontSee(__('filum::filum.sidebar.groups'))
        ->set('groupName', 'Couriers')
        ->call('createGroup')
        // Switched off is not a mistake the person typing made, so they are told
        // nothing: the field they typed into is not on screen either.
        ->assertHasNoErrors('groupName');

    expect(Conversation::query()->where('kind', 'group')->exists())->toBeFalse();
});

it('answers an invitation that is not there without throwing', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('acceptInvitation', 987654)
        ->assertSet('conversation', null)
        ->call('declineInvitation', 987654)
        ->assertSet('conversation', null);
});

it('refuses to accept its way into a group it was never invited to', function (): void {
    $group = app(Groups::class)->create($this->giorgi, 'Theirs');

    Livewire::test(ChatPanel::class)
        ->call('acceptInvitation', $group->id)
        ->assertSet('conversation', null);

    expect($group->fresh()?->includes($this->nino->id))->toBeFalse();
});

it('does nothing with the group actions when nothing is open', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('inviteToGroup', (string) $this->giorgi->id)
        ->call('leaveGroup')
        ->call('deleteGroup')
        ->assertHasNoErrors('groupName')
        ->assertSet('conversation', null);
});

it('does nothing with the group actions in a direct conversation', function (): void {
    Livewire::test(ChatPanel::class)
        ->call('selectUser', (string) $this->giorgi->id)
        ->call('inviteToGroup', (string) $this->giorgi->id)
        ->call('leaveGroup')
        ->call('deleteGroup')
        ->assertHasNoErrors('groupName')
        // Still open: none of the three applied, so none of them closed it.
        ->assertSet('conversation', Conversation::query()->firstOrFail()->id);
});

it('swallows a second invitation to the same colleague', function (): void {
    $group = app(Groups::class)->create($this->nino, 'Couriers');

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->call('inviteToGroup', (string) $this->giorgi->id)
        ->call('inviteToGroup', (string) $this->giorgi->id)
        ->assertOk();

    expect($group->participants()->where('user_id', $this->giorgi->id)->count())->toBe(1);
});

it('picks up a new invitation on a tick', function (): void {
    $panel = Livewire::test(ChatPanel::class)->call('tick');

    $group = app(Groups::class)->create($this->giorgi, 'Couriers');
    app(Groups::class)->invite($group, $this->giorgi, $this->nino->id);

    // The fingerprint counts invitations, so the skip-render that protects the
    // composer does not swallow this.
    $panel->call('tick')->assertSee('Couriers');
});
