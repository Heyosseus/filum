<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Groups;

use Carbon\CarbonImmutable;
use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Exceptions\AlreadyInvited;
use Heyosseus\Filum\Exceptions\NotAGroup;
use Heyosseus\Filum\Exceptions\NotAParticipant;
use Heyosseus\Filum\Exceptions\NotInvited;
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
    /**
     * Invite a colleague. Pending until they accept, so nobody is silently
     * subscribed to a conversation they never agreed to join.
     */
    public function invite(Conversation $group, Authenticatable $actor, int|string $userId): Participant
    {
        $this->assertGroup($group);
        $this->assertMember($group, $actor);

        $existing = $this->row($group, $userId);

        if ($existing instanceof Participant && $existing->state !== 'left') {
            throw AlreadyInvited::of($group->id);
        }

        $invitedBy = $this->users->id($actor);

        // Someone who left keeps their row, and with it the message they had read
        // up to, so a re-invite resumes rather than replaying the whole thread.
        if ($existing instanceof Participant) {
            $existing->forceFill(['state' => 'invited', 'invited_by_id' => $invitedBy])->save();

            return $existing;
        }

        return Participant::query()->create([
            'conversation_id' => $group->id,
            'user_id' => $userId,
            'state' => 'invited',
            'invited_by_id' => $invitedBy,
        ]);
    }

    public function accept(Conversation $group, Authenticatable $user): void
    {
        $this->pending($group, $user)
            ->forceFill(['state' => 'joined', 'joined_at' => CarbonImmutable::now()])
            ->save();
    }

    public function decline(Conversation $group, Authenticatable $user): void
    {
        // The same terminal state as leaving: a separate 'declined' would behave
        // identically everywhere and only add a branch.
        $this->pending($group, $user)->forceFill(['state' => 'left'])->save();
    }

    private function pending(Conversation $group, Authenticatable $user): Participant
    {
        $this->assertGroup($group);

        $row = $this->row($group, $this->users->id($user));

        if (! $row instanceof Participant || $row->state !== 'invited') {
            throw NotInvited::of($group->id);
        }

        return $row;
    }

    private function assertMember(Conversation $group, Authenticatable $actor): void
    {
        if (! $group->includes($this->users->id($actor))) {
            throw NotAParticipant::of($group->id);
        }
    }

    /**
     * A participant row in any state, which is what the lifecycle needs and what
     * Conversations::participant deliberately will not give it.
     */
    private function row(Conversation $group, int|string $userId): ?Participant
    {
        return Participant::query()
            ->where('conversation_id', $group->id)
            ->where('user_id', $userId)
            ->first();
    }

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
