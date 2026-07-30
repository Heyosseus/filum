<?php

declare(strict_types=1);

use Heyosseus\Filum\Filum;
use Heyosseus\Filum\Groups\Groups;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Participant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;

/**
 * The registered callback for a channel, resolved from Laravel's own registry.
 *
 * Read back out of the broadcaster rather than reimplemented here, because the
 * thing under test is the callback the application will actually authorize
 * against: a routes/channels.php that stopped narrowing, or stopped being loaded
 * at all, has to fail this.
 */
function channelCallback(string $pattern): Closure
{
    $callback = Broadcast::getChannels()->get($pattern);

    if (! $callback instanceof Closure) {
        throw new RuntimeException("Filum registered no callback for {$pattern}.");
    }

    return $callback;
}

function mayJoin(Authenticatable $user, int $conversationId): bool
{
    return (bool) channelCallback('filum.conversation.{conversation}')($user, $conversationId);
}

function mayWatchPresence(Authenticatable $user): bool
{
    return (bool) channelCallback('filum.presence')($user);
}

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->group = app(Groups::class)->create($this->giorgi, 'Couriers');
});

it('authorises a joined member onto the conversation channel', function (): void {
    app(Groups::class)->invite($this->group, $this->giorgi, $this->nino->id);
    app(Groups::class)->accept($this->group, $this->nino);

    expect(mayJoin($this->nino, $this->group->id))->toBeTrue();
});

it('refuses an invited participant who has not accepted', function (): void {
    app(Groups::class)->invite($this->group, $this->giorgi, $this->nino->id);

    // The highest-value line in the release: a pending invitee is kept off the
    // socket, not merely out of the sidebar, so they cannot read the thread they
    // have not agreed to join by subscribing to it.
    expect(mayJoin($this->nino, $this->group->id))->toBeFalse();
});

it('refuses a participant who has left', function (): void {
    $groups = app(Groups::class);
    $groups->invite($this->group, $this->giorgi, $this->nino->id);
    $groups->accept($this->group, $this->nino);
    $groups->leave($this->group, $this->nino);

    expect(mayJoin($this->nino, $this->group->id))->toBeFalse();
});

it('refuses somebody who was never in the conversation', function (): void {
    expect(mayJoin($this->nino, $this->group->id))->toBeFalse();
});

it('refuses a conversation that does not exist', function (): void {
    expect(mayJoin($this->nino, 987654))->toBeFalse();
});

it('refuses a joined member the gate turns away', function (): void {
    $groups = app(Groups::class);
    $groups->invite($this->group, $this->giorgi, $this->nino->id);
    $groups->accept($this->group, $this->nino);

    // Membership is not the only question. One gate governs every surface, so a
    // person the page would not have shown the chat to cannot subscribe to it
    // either -- however joined they are.
    Filum::auth(static fn (?Authenticatable $user): bool => false);

    expect(mayJoin($this->nino, $this->group->id))->toBeFalse();
});

it('authorises a direct conversation the same way', function (): void {
    $direct = Conversation::query()->create(['kind' => 'direct', 'key' => 'nino:giorgi']);

    Participant::query()->create([
        'conversation_id' => $direct->id,
        'user_id' => $this->nino->id,
        'state' => 'joined',
    ]);

    expect(mayJoin($this->nino, $direct->id))->toBeTrue()
        ->and(mayJoin($this->giorgi, $direct->id))->toBeFalse();
});

it('lets anyone the gate admits watch presence', function (): void {
    expect(mayWatchPresence($this->nino))->toBeTrue();
});

it('keeps presence from anyone the gate refuses', function (): void {
    Filum::auth(static fn (?Authenticatable $user): bool => false);

    expect(mayWatchPresence($this->nino))->toBeFalse();
});
