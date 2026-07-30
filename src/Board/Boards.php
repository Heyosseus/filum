<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Board;

use Heyosseus\Filum\Contracts\PresenceStore;
use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Conversations\ConversationKey;
use Heyosseus\Filum\Groups\Groups;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Conversation;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class Boards
{
    public function __construct(
        private UserProvider $users,
        private PresenceStore $presence,
        private Messages $messages,
        private Groups $groups,
    ) {}

    public function for(Authenticatable $user, string $search = ''): Board
    {
        $needle = mb_strtolower(trim($search));
        $active = $this->presence->active();
        $here = [];
        $away = [];

        foreach ($this->users->chattable($user) as $colleague) {
            $row = $this->person($user, $colleague);

            if ($needle !== '' && ! str_contains(mb_strtolower($row['name']), $needle)) {
                continue;
            }

            in_array($this->users->id($colleague), $active, true)
                ? $here[] = $row
                : $away[] = $row;
        }

        return new Board($here, $away, $this->groupRows($user), $this->invitationRows($user));
    }

    /**
     * The colleagues who could still be asked into a group.
     *
     * Anyone already invited or joined is left out -- inviting them again is the
     * one thing Groups::invite refuses. Somebody who left is offered again, which
     * is what makes a re-invite possible at all: their row survives, and with it
     * the message they had read up to.
     *
     * Not part of Board: the board is the same for every render, while this
     * depends on which group is open, and folding it in would mean recomputing it
     * on every render that has no group open at all.
     *
     * @return list<array{id: string, name: string, avatar: string|null, unread: int}>
     */
    public function invitableFor(Authenticatable $user, Conversation $group): array
    {
        $taken = array_map(strval(...), $group->participants()
            ->whereIn('state', ['invited', 'joined'])
            ->pluck('user_id')
            ->all());

        $rows = [];

        foreach ($this->users->chattable($user) as $colleague) {
            if (in_array((string) $this->users->id($colleague), $taken, true)) {
                continue;
            }

            $rows[] = $this->person($user, $colleague);
        }

        return $rows;
    }

    /**
     * @return array{id: string, name: string, avatar: string|null, unread: int}
     */
    private function person(Authenticatable $user, Authenticatable $colleague): array
    {
        $id = $this->users->id($colleague);

        // Reading the unread count needs the conversation, but listing a colleague
        // must never create one -- otherwise opening the board would write a row
        // per person.
        $conversation = Conversation::query()
            ->where('key', ConversationKey::for([$this->users->id($user), $id]))
            ->first();

        return [
            'id' => (string) $id,
            'name' => $this->users->name($colleague),
            'avatar' => $this->users->avatar($colleague),
            'unread' => $conversation instanceof Conversation
                ? $this->messages->unreadIn($conversation, $user)
                : 0,
        ];
    }

    /**
     * @return list<array{id: int, name: string, members: int, unread: int}>
     */
    private function groupRows(Authenticatable $user): array
    {
        return array_values($this->groups->for($user)
            ->map(fn (Conversation $group): array => [
                'id' => $group->id,
                'name' => (string) $group->name,
                'members' => $group->participants()->where('state', 'joined')->count(),
                'unread' => $this->messages->unreadIn($group, $user),
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, name: string, invitedBy: string}>
     */
    private function invitationRows(Authenticatable $user): array
    {
        $userId = $this->users->id($user);

        return array_values($this->groups->invitationsFor($user)
            ->map(function (Conversation $group) use ($userId): array {
                $row = $group->participants()->where('user_id', $userId)->first();
                $inviter = $row === null ? null : $this->users->find((string) $row->invited_by_id);

                return [
                    'id' => $group->id,
                    'name' => (string) $group->name,
                    'invitedBy' => $inviter instanceof Authenticatable ? $this->users->name($inviter) : '',
                ];
            })
            ->all());
    }
}
