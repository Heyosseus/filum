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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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

    public function leave(Conversation $group, Authenticatable $user): void
    {
        $this->assertGroup($group);

        $userId = $this->users->id($user);
        $row = $this->row($group, $userId);

        if (! $row instanceof Participant || $row->state !== 'joined') {
            throw NotAParticipant::of($group->id);
        }

        $row->forceFill(['state' => 'left'])->save();

        if ((string) $group->owner_id === (string) $userId) {
            $this->reassignOwner($group, $userId);
        }
    }

    public function remove(Conversation $group, Authenticatable $actor, int|string $userId): void
    {
        $this->assertOwner($group, $actor);

        if ((string) $userId === (string) $group->owner_id) {
            throw new InvalidArgumentException('The owner leaves a group rather than removing themselves.');
        }

        $row = $this->row($group, $userId);

        if (! $row instanceof Participant || $row->state === 'left') {
            throw NotAParticipant::of($group->id);
        }

        // Covers a pending invitation too: withdrawing one and ejecting a member
        // are the same transition to the same terminal state.
        $row->forceFill(['state' => 'left'])->save();
    }

    public function delete(Conversation $group, Authenticatable $actor): void
    {
        $this->assertOwner($group, $actor);

        $group->delete();
    }

    /**
     * The groups this user has joined, most recently active first.
     *
     * @return Collection<int, Conversation>
     */
    public function for(Authenticatable $user): Collection
    {
        return $this->listing($user, 'joined')
            // COALESCE rather than a plain order: a group created a moment ago has
            // no last_message_at, and DESC would sort those NULLs to opposite ends
            // on Postgres and SQLite.
            ->orderByDesc(DB::raw('coalesce(last_message_at, created_at)'))
            ->get();
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function invitationsFor(Authenticatable $user): Collection
    {
        return $this->listing($user, 'invited')->orderBy('name')->get();
    }

    /**
     * @return Builder<Conversation>
     */
    private function listing(Authenticatable $user, string $state): Builder
    {
        $query = Conversation::query()->where('kind', 'group');

        if ($this->config->get('filum.groups.enabled', true) !== true) {
            // Absent, not merely hidden: nothing downstream has to know groups
            // were switched off.
            return $query->whereRaw('1 = 0');
        }

        $userId = $this->users->id($user);

        return $query->whereHas(
            'participants',
            fn (Builder $participants): Builder => $participants->where('user_id', $userId)->where('state', $state),
        );
    }

    /**
     * Hand the group to the longest-joined member left, or delete it if there is
     * nobody. An owner leaving must not strand a group nobody can manage.
     */
    private function reassignOwner(Conversation $group, int|string $leavingId): void
    {
        $next = Participant::query()
            ->where('conversation_id', $group->id)
            ->where('state', 'joined')
            ->whereNot('user_id', $leavingId)
            ->orderBy('joined_at')
            ->orderBy('id')
            ->first();

        if (! $next instanceof Participant) {
            $group->delete();

            return;
        }

        $group->forceFill(['owner_id' => $next->user_id])->save();
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
