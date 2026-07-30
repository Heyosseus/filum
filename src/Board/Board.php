<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Board;

/**
 * What the board pane shows, and nothing about how it got there.
 *
 * A value object rather than a bag of Livewire properties: the pane has four
 * sections, and giving them names makes the Blade read as the design does.
 *
 * @phpstan-type Person array{id: string, name: string, avatar: string|null, unread: int}
 * @phpstan-type Group array{id: int, name: string, members: int, unread: int}
 * @phpstan-type Invitation array{id: int, name: string, invitedBy: string}
 */
final readonly class Board
{
    /**
     * @param  list<Person>  $here
     * @param  list<Person>  $away
     * @param  list<Group>  $groups
     * @param  list<Invitation>  $invitations
     */
    public function __construct(
        public array $here,
        public array $away,
        public array $groups,
        public array $invitations,
    ) {}
}
