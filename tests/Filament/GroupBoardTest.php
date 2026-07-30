<?php

declare(strict_types=1);

use Heyosseus\Filum\Groups\Groups;
use Heyosseus\Filum\Livewire\ChatPanel;
use Heyosseus\Filum\Messages\Messages;
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

it('invites a colleague into a group it is in', function (): void {
    $group = app(Groups::class)->create($this->nino, 'Couriers');

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->call('inviteToGroup', (string) $this->giorgi->id);

    expect($group->participants()->where('user_id', $this->giorgi->id)->value('state'))->toBe('invited');
});

it('offers the colleagues not in the group yet, and stops offering the one invited', function (): void {
    $group = app(Groups::class)->create($this->nino, 'Couriers');

    $panel = Livewire::test(ChatPanel::class)->call('selectConversation', $group->id);

    // The wire:click, not merely the name: the board lists Giorgi too, so only the
    // control's own call proves the picker is what is on screen.
    $panel->assertSee(__('filum::filum.sidebar.invite'))
        ->assertSeeHtml("inviteToGroup('{$this->giorgi->id}')")
        ->call('inviteToGroup', (string) $this->giorgi->id);

    expect($group->participants()->where('user_id', $this->giorgi->id)->value('state'))->toBe('invited');

    // Already asked, and they were the only colleague: with nobody left to invite
    // the disclosure is gone rather than open and empty.
    $panel->assertDontSeeHtml('inviteToGroup(')
        ->assertDontSee(__('filum::filum.sidebar.invite'));
});

it('offers somebody who left the group again', function (): void {
    $groups = app(Groups::class);
    $group = $groups->create($this->nino, 'Couriers');
    $groups->invite($group, $this->nino, $this->giorgi->id);
    $groups->accept($group, $this->giorgi);
    $groups->leave($group, $this->giorgi);

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->assertSeeHtml("inviteToGroup('{$this->giorgi->id}')");
});

it('counts the members of a group in the singular and the plural', function (): void {
    $groups = app(Groups::class);
    $group = $groups->create($this->nino, 'Couriers');

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->assertSee('1 member')
        ->assertDontSee('1 members');

    $groups->invite($group, $this->nino, $this->giorgi->id);
    $groups->accept($group, $this->giorgi);

    Livewire::test(ChatPanel::class)
        ->call('selectConversation', $group->id)
        ->assertSee('2 members');
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
        // Keyed 'group' and rendered in the header, so the drawer -- which swaps
        // the board out while a thread is open -- still shows the refusal.
        ->assertHasErrors('group')
        ->assertSee(__('filum::filum.sidebar.not_yours_to_delete'));

    expect(Conversation::query()->find($group->id))->not->toBeNull();
});

it('shows nothing of a thread it was never in, even with the id set directly', function (): void {
    $group = app(Groups::class)->create($this->giorgi, 'Theirs');
    app(Messages::class)->send($group, $this->giorgi, 'the float is under the till');

    Livewire::test(ChatPanel::class)
        // Straight onto the public property. The browser can do exactly this, and
        // it is the one path selectConversation's own check never sees.
        ->set('conversation', $group->id)
        // The whole point of the test: existence is not permission, and a forged
        // id must not put somebody else's messages into the response.
        ->assertDontSee('the float is under the till')
        ->assertDontSee('Theirs')
        ->assertDontSee(__('filum::filum.sidebar.leave_group'))
        // The board stands in, exactly as if nothing were open.
        ->assertSee(__('filum::filum.conversation.none_selected'))
        // And nothing done from that forged id takes effect either.
        ->call('inviteToGroup', (string) $this->giorgi->id)
        ->call('leaveGroup')
        ->call('deleteGroup')
        ->assertHasNoErrors();

    expect($group->participants()->count())->toBe(1)
        ->and(Conversation::query()->find($group->id))->not->toBeNull();
});

it('shows a thread it is in when the id is set the same direct way', function (): void {
    $groups = app(Groups::class);
    $group = $groups->create($this->giorgi, 'Couriers');
    $groups->invite($group, $this->giorgi, $this->nino->id);
    $groups->accept($group, $this->nino);
    app(Messages::class)->send($group, $this->giorgi, 'shift starts at six');

    // The control for the test above. Without it that one would pass just as well
    // against a component that renders nothing whenever the property is set
    // directly, and would prove nothing about membership.
    Livewire::test(ChatPanel::class)
        ->set('conversation', $group->id)
        ->assertSee('shift starts at six')
        ->assertSee('Couriers')
        ->assertSee(__('filum::filum.sidebar.leave_group'));
});

it('lets an open thread fall away when the group is deleted underneath it', function (): void {
    $group = app(Groups::class)->create($this->nino, 'Couriers');
    app(Messages::class)->send($group, $this->nino, 'shift starts at six');

    $panel = Livewire::test(ChatPanel::class)->call('selectConversation', $group->id);

    // Deleted behind the component's back: by the owner in another tab, or by a
    // console command. The id is still sitting in this component's state, and the
    // conversation is re-read every call precisely so that stops mattering.
    $group->delete();

    $panel->call('tick')
        ->assertDontSee('shift starts at six')
        ->assertSee(__('filum::filum.conversation.none_selected'));
});

it('keeps an open group readable when groups are switched off underneath it', function (): void {
    $group = app(Groups::class)->create($this->nino, 'Couriers');
    app(Messages::class)->send($group, $this->nino, 'shift starts at six');

    // Switching groups off hides the board section and refuses new groups, but
    // somebody already joined is still joined. The member count is read off the
    // board, which no longer lists the group, so it reads as none rather than
    // going back to the database for a second opinion.
    config()->set('filum.groups.enabled', false);

    Livewire::test(ChatPanel::class)
        ->set('conversation', $group->id)
        ->assertSee('shift starts at six')
        ->assertDontSee(__('filum::filum.sidebar.groups'))
        ->assertSee('0 members');
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
