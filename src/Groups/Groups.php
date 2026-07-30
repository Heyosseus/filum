<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Groups;

use Carbon\CarbonImmutable;
use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Exceptions\NotAGroup;
use Heyosseus\Filum\Exceptions\NotTheOwner;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Participant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

/**
 * Group conversations and the invitations into them.
 *
 * A group is a conversation with no derived key -- membership changes over time,
 * so it cannot have one -- an owner, and participants whose state says whether
 * they have accepted yet.
 */
final readonly class Groups
{
    private const int NAME_LIMIT = 120;

    public function __construct(
        private UserProvider $users,
        private Repository $config,
    ) {}

    public function create(Authenticatable $owner, string $name): Conversation
    {
        $this->assertEnabled();

        $ownerId = $this->users->id($owner);
        $now = CarbonImmutable::now();

        $group = Conversation::query()->create([
            'kind' => 'group',
            'name' => $this->clean($name),
            'owner_id' => $ownerId,
        ]);

        Participant::query()->create([
            'conversation_id' => $group->id,
            'user_id' => $ownerId,
            'state' => 'joined',
            'joined_at' => $now,
        ]);

        return $group;
    }

    public function rename(Conversation $group, Authenticatable $actor, string $name): void
    {
        $this->assertOwner($group, $actor);

        $group->forceFill(['name' => $this->clean($name)])->save();
    }

    /**
     * Trim and bound a name. An unnamed group is not a group.
     */
    private function clean(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('A group needs a name.');
        }

        return mb_substr($name, 0, self::NAME_LIMIT);
    }

    private function assertEnabled(): void
    {
        if ($this->config->get('filum.groups.enabled', true) !== true) {
            throw NotAGroup::disabled();
        }
    }

    private function assertGroup(Conversation $group): void
    {
        $this->assertEnabled();

        if (! $group->isGroup()) {
            throw NotAGroup::of($group->id);
        }
    }

    private function assertOwner(Conversation $group, Authenticatable $actor): void
    {
        $this->assertGroup($group);

        if ((string) $group->owner_id !== (string) $this->users->id($actor)) {
            throw NotTheOwner::of($group->id);
        }
    }
}
