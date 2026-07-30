<?php

declare(strict_types=1);

use Heyosseus\Filum\Board\Boards;
use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Groups\Groups;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Presence\Heartbeat;

beforeEach(function (): void {
    $this->nino = $this->user('Nino');
    $this->giorgi = $this->user('Giorgi');
    $this->dato = $this->user('Dato');
});

it('splits colleagues by presence and never lists you', function (): void {
    app(Heartbeat::class)->beat($this->giorgi);

    $board = app(Boards::class)->for($this->nino);

    expect(array_column($board->here, 'name'))->toBe(['Giorgi'])
        ->and(array_column($board->away, 'name'))->toBe(['Dato']);
});

it('filters colleagues by search without touching groups', function (): void {
    app(Groups::class)->create($this->nino, 'Couriers');

    $board = app(Boards::class)->for($this->nino, 'dat');

    expect(array_column($board->away, 'name'))->toBe(['Dato'])
        ->and(array_column($board->groups, 'name'))->toBe(['Couriers']);
});

it('carries a group with its member count', function (): void {
    $groups = app(Groups::class);
    $group = $groups->create($this->nino, 'Couriers');
    $groups->invite($group, $this->nino, $this->giorgi->id);
    $groups->accept($group, $this->giorgi);
    $groups->invite($group, $this->nino, $this->dato->id);

    $board = app(Boards::class)->for($this->nino);

    // Two, not three: an invitation is not a member.
    expect($board->groups[0]['members'])->toBe(2);
});

it('carries an invitation with who sent it', function (): void {
    $group = app(Groups::class)->create($this->giorgi, 'Couriers');
    app(Groups::class)->invite($group, $this->giorgi, $this->nino->id);

    $board = app(Boards::class)->for($this->nino);

    expect($board->invitations[0]['name'])->toBe('Couriers')
        ->and($board->invitations[0]['invitedBy'])->toBe('Giorgi');
});

it('names no inviter when the inviting user is gone', function (): void {
    $group = app(Groups::class)->create($this->giorgi, 'Couriers');
    app(Groups::class)->invite($group, $this->giorgi, $this->nino->id);

    $this->giorgi->delete();

    expect(app(Boards::class)->for($this->nino)->invitations[0]['invitedBy'])->toBe('');
});

it('finds nobody when the search matches nobody', function (): void {
    $board = app(Boards::class)->for($this->nino, 'zzzz');

    expect($board->here)->toBeEmpty()
        ->and($board->away)->toBeEmpty();
});

it('carries a colleague unread count once a conversation exists', function (): void {
    $conversation = app(Conversations::class)->between($this->nino->id, $this->giorgi->id);
    app(Messages::class)->send($conversation, $this->giorgi, 'Hey');

    $board = app(Boards::class)->for($this->nino);
    $giorgiRow = collect($board->away)->firstWhere('name', 'Giorgi');

    expect($giorgiRow['unread'])->toBe(1);
});
