<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Livewire;

use Heyosseus\Filum\Board\Board;
use Heyosseus\Filum\Board\Boards;
use Heyosseus\Filum\Contracts\PresenceStore;
use Heyosseus\Filum\Contracts\Transport;
use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Exceptions\AlreadyInvited;
use Heyosseus\Filum\Exceptions\NotAGroup;
use Heyosseus\Filum\Exceptions\NotAParticipant;
use Heyosseus\Filum\Exceptions\NotInvited;
use Heyosseus\Filum\Exceptions\NotTheOwner;
use Heyosseus\Filum\Exceptions\RateLimited;
use Heyosseus\Filum\Filum;
use Heyosseus\Filum\Groups\Groups;
use Heyosseus\Filum\Messages\Messages;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Presence\Heartbeat;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Component;

/**
 * The chat itself, in one component.
 *
 * The page and the overlay both mount this; $mode changes the chrome around it
 * and nothing else, so there is one set of behaviour to reason about and one set
 * to test. The sidebar, thread and composer are Blade partials rather than
 * separate Livewire components: keeping them in one component avoids threading
 * state through inter-component events, whose API is exactly where Livewire 3
 * and 4 differ most.
 */
final class ChatPanel extends Component
{
    /** Either 'page' or 'overlay'. */
    public string $mode = 'page';

    /**
     * The open conversation, direct or group.
     *
     * A conversation id rather than a user id: a group has no single other
     * person, and unifying here takes the "who is the partner" special case out
     * of every path that only needed to know which thread is open.
     */
    public ?int $conversation = null;

    /** Whether the overlay is expanded. Ignored in page mode. */
    public bool $open = false;

    public string $body = '';

    public string $search = '';

    /** What the board's new-group field holds, and where its refusals are shown. */
    public string $groupName = '';

    /** What the group header's rename field holds, and where its refusals are shown. */
    public string $rename = '';

    /**
     * The oldest message to show, for scrollback. Null means the newest page.
     *
     * It only ever moves backwards, so loading earlier messages grows the thread
     * rather than sliding a window along it.
     */
    public ?int $from = null;

    /**
     * A fingerprint of everything the last render actually showed.
     *
     * Carried in the component's own state because a Livewire component is built
     * afresh for every request: without it, a tick has nothing to compare against
     * and cannot tell a quiet poll from a real change.
     */
    public string $seen = '';

    /**
     * conversation()'s answer for this request, and the id it answered for.
     *
     * Private, so Livewire neither serialises it nor carries it between requests:
     * the memo is per request only, and a request is the longest a "which
     * conversation is open, and may I see it" answer can safely be trusted for.
     *
     * Invalidated two ways, because this is the security seam and one way is not
     * enough. The id is part of the key, so opening or closing a conversation
     * cannot be answered from a memo about a different one; and forget() is called
     * after every action that changes membership or existence, so an accept, a
     * leave or a delete cannot be read past. The alternative -- memoising inside
     * render() alone -- would leave the five or six calls an action makes
     * un-memoised for no gain in safety.
     */
    private ?int $memoisedFor = null;

    private ?Conversation $memoised = null;

    public function mount(string $mode = 'page'): void
    {
        $this->mode = $mode === 'overlay' ? 'overlay' : 'page';
    }

    public function render(): View
    {
        $user = $this->user();
        $thread = $this->thread();
        $descriptor = app(Transport::class)->descriptor();
        $conversation = $this->conversation();

        // Assembled outside the component: what the board shows is a question
        // about the whole account, not about this one piece of chrome, and both
        // modes ask it identically.
        $board = $user instanceof Authenticatable
            ? app(Boards::class)->for($user, $this->search)
            : new Board([], [], [], []);

        $group = $conversation instanceof Conversation && $conversation->isGroup() ? $conversation : null;

        // Recorded on the way out, so a tick always compares against what is
        // genuinely on screen rather than against a guess made somewhere else.
        $this->seen = $this->fingerprint();

        // Resolved through the factory rather than the view() helper: a package's
        // namespaced views are registered at runtime, so static analysis cannot
        // know 'filum::…' is a real view-string, and the factory takes a plain one.
        return app(Factory::class)->make('filum::livewire.chat-panel', [
            'me' => $user,
            'board' => $board,
            'thread' => $thread,
            'partner' => $this->partner(),
            'group' => $group,
            'invitable' => $this->invitable($user, $group),
            'roster' => $this->roster($user, $group),
            'members' => $this->members($board, $group),
            'poll' => $descriptor['poll'],
            'driver' => $descriptor['driver'],
            'conversationId' => $conversation?->id,
            'hasOlder' => $this->hasOlder($thread),
        ]);
    }

    /**
     * The colleagues who could still be asked into the open group.
     *
     * Asked only when there is a group open to ask about, so a board on its own
     * costs nothing.
     *
     * @return list<array{id: string, name: string}>
     */
    private function invitable(?Authenticatable $user, ?Conversation $group): array
    {
        if (! $user instanceof Authenticatable || ! $group instanceof Conversation) {
            return [];
        }

        return app(Boards::class)->invitableFor($user, $group);
    }

    /**
     * Who the owner of the open group could remove.
     *
     * Empty for anyone but the owner, because removal is the owner's alone: a
     * roster with no control against any row would be a list nobody could act on,
     * and the header sits in a 26rem drawer where every line has to earn its place.
     *
     * @return list<array{id: string, name: string, pending: bool}>
     */
    private function roster(?Authenticatable $user, ?Conversation $group): array
    {
        if (! $user instanceof Authenticatable || ! $group instanceof Conversation) {
            return [];
        }

        if ((string) $group->owner_id !== (string) app(UserProvider::class)->id($user)) {
            return [];
        }

        return app(Boards::class)->rosterFor($user, $group);
    }

    /**
     * How many people are in the open group, read off the board rather than
     * counted again: the board already asked, and two answers can disagree.
     *
     * Zero when nothing is open, and zero as the fallback if the open group is
     * somehow not on the board -- which, now that a group only resolves at all
     * when the viewer has joined it and groups are switched on, is a shape the
     * types still allow rather than a state anything can reach.
     */
    private function members(Board $board, ?Conversation $group): int
    {
        if (! $group instanceof Conversation) {
            return 0;
        }

        return array_column($board->groups, 'members', 'id')[$group->id] ?? 0;
    }

    /**
     * Open the direct conversation with a colleague, creating it on first open.
     */
    public function selectUser(string $id): void
    {
        $me = $this->user();
        $users = app(UserProvider::class);
        $partner = $users->find($id);

        if (! $me instanceof Authenticatable || ! $partner instanceof Authenticatable) {
            return;
        }

        $this->open(app(Conversations::class)->between($users->id($me), $users->id($partner)));
    }

    /**
     * Open a conversation by id -- how a group is opened, and how the board
     * addresses anything that is not a person.
     */
    public function selectConversation(int $id): void
    {
        $me = $this->user();
        $conversation = Conversation::query()->find($id);

        if (! $me instanceof Authenticatable || ! $conversation instanceof Conversation) {
            return;
        }

        // Membership, not merely existence: this is a public Livewire method, so
        // the id arrives from the browser. A stale id from an old page is turned
        // away in silence rather than thrown at somebody as an error.
        if (! $conversation->includes(app(UserProvider::class)->id($me))) {
            return;
        }

        $this->open($conversation);
    }

    private function open(Conversation $conversation): void
    {
        $this->conversation = $conversation->id;
        $this->from = null;
        $this->clearComposer();

        $me = $this->user();

        if ($me instanceof Authenticatable) {
            app(Messages::class)->markRead($conversation, $me);
        }
    }

    /**
     * Close the open conversation and go back to the board.
     *
     * The drawer shows one pane at a time, so leaving a thread is a real action
     * there rather than just looking elsewhere on screen.
     */
    public function deselect(): void
    {
        $this->conversation = null;
        $this->from = null;
        $this->clearComposer();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    /**
     * Start a group from the board's inline field and open it.
     *
     * Every group action below catches its typed failure and either tells the
     * person or does nothing: all of them are reachable from a browser-supplied
     * id, so none of them may reach an error page.
     */
    public function createGroup(): void
    {
        $me = $this->user();

        if (! $me instanceof Authenticatable) {
            return;
        }

        try {
            $group = app(Groups::class)->create($me, $this->groupName);
        } catch (InvalidArgumentException) {
            $this->addError('groupName', __('filum::filum.sidebar.group_needs_name'));

            return;
        } catch (NotAGroup) {
            return;
        }

        $this->clearGroupName();
        $this->open($group);
    }

    /**
     * Empty the new-group field, on the server and in the browser.
     *
     * The same problem the composer has, for the same reason: the input carries
     * wire:ignore so that a poll cannot morph a half-typed name back to the
     * server's empty string, which also means emptying the property is not enough
     * on its own -- the browser has to be told, and only once a group was made.
     */
    private function clearGroupName(): void
    {
        $this->groupName = '';
        $this->dispatch('filum-group-name-cleared');
    }

    public function acceptInvitation(int $id): void
    {
        $this->respondToInvitation($id, true);
    }

    public function declineInvitation(int $id): void
    {
        $this->respondToInvitation($id, false);
    }

    private function respondToInvitation(int $id, bool $accept): void
    {
        $me = $this->user();
        $group = Conversation::query()->find($id);

        if (! $me instanceof Authenticatable || ! $group instanceof Conversation) {
            return;
        }

        try {
            $accept
                ? app(Groups::class)->accept($group, $me)
                : app(Groups::class)->decline($group, $me);
        } catch (NotInvited|NotAGroup) {
            return;
        }

        $this->forget();

        if ($accept) {
            $this->open($group);
        }
    }

    public function inviteToGroup(string $userId): void
    {
        $me = $this->user();
        $group = $this->conversation();

        if (! $me instanceof Authenticatable || ! $group instanceof Conversation) {
            return;
        }

        try {
            // InvalidArgumentException among them because the id comes from the
            // browser: a forged one that names nobody is turned away in silence
            // rather than written as a row nobody could ever accept or withdraw.
            app(Groups::class)->invite($group, $me, $userId);
        } catch (AlreadyInvited|NotAParticipant|NotAGroup|InvalidArgumentException) {
            return;
        }

        $this->forget();
    }

    /**
     * Rename the open group from the header's inline field. Owner only.
     */
    public function renameGroup(): void
    {
        $me = $this->user();
        $group = $this->conversation();

        if (! $me instanceof Authenticatable || ! $group instanceof Conversation) {
            return;
        }

        try {
            app(Groups::class)->rename($group, $me, $this->rename);
        } catch (InvalidArgumentException) {
            $this->addError('rename', __('filum::filum.sidebar.group_needs_name'));

            return;
        } catch (NotTheOwner) {
            // Keyed 'group' for the same reason deleteGroup's refusal is: it belongs
            // beside the control that was pressed, and that control is in the header.
            $this->addError('group', __('filum::filum.sidebar.not_yours_to_rename'));

            return;
        } catch (NotAGroup) {
            return;
        }

        $this->clearRename();
        $this->forget();
    }

    /**
     * Empty the rename field, on the server and in the browser.
     *
     * wire:ignore on the input for the reason the composer and the new-group field
     * carry it -- a poll morphs a half-typed name back to the server's empty
     * string -- which means the browser has to be told separately, and only once a
     * rename actually took.
     */
    private function clearRename(): void
    {
        $this->rename = '';
        $this->dispatch('filum-group-renamed');
    }

    /**
     * Put somebody out of the open group, or withdraw their invitation. Owner only.
     */
    public function removeMember(string $userId): void
    {
        $me = $this->user();
        $group = $this->conversation();

        if (! $me instanceof Authenticatable || ! $group instanceof Conversation) {
            return;
        }

        try {
            app(Groups::class)->remove($group, $me, $userId);
        } catch (NotTheOwner) {
            $this->addError('group', __('filum::filum.sidebar.not_yours_to_remove'));

            return;
        } catch (NotAParticipant|NotAGroup|InvalidArgumentException) {
            // InvalidArgumentException is an owner aimed at themselves. The roster
            // never renders a control against the owner, so this is only reachable
            // from a forged call, and an owner who wants out has Leave group.
            return;
        }

        $this->forget();
    }

    public function leaveGroup(): void
    {
        $me = $this->user();
        $group = $this->conversation();

        if (! $me instanceof Authenticatable || ! $group instanceof Conversation) {
            return;
        }

        try {
            app(Groups::class)->leave($group, $me);
        } catch (NotAParticipant|NotAGroup) {
            return;
        }

        $this->forget();
        $this->deselect();
    }

    public function deleteGroup(): void
    {
        $me = $this->user();
        $group = $this->conversation();

        if (! $me instanceof Authenticatable || ! $group instanceof Conversation) {
            return;
        }

        try {
            app(Groups::class)->delete($group, $me);
        } catch (NotTheOwner) {
            // Keyed 'group' rather than 'groupName', and rendered in the group
            // header: groupName's error block lives in the board, and the drawer
            // swaps the board out while a thread is open -- so the refusal would
            // arrive in a pane nobody is looking at.
            $this->addError('group', __('filum::filum.sidebar.not_yours_to_delete'));

            return;
        } catch (NotAGroup) {
            return;
        }

        $this->forget();
        $this->deselect();
    }

    /**
     * Send what is in the composer.
     *
     * Both failure modes are shown against the field rather than thrown: a
     * person who typed too much or too fast should be told, not shown a stack
     * trace.
     */
    public function send(): void
    {
        $conversation = $this->conversation();
        $me = $this->user();

        if (! $conversation instanceof Conversation || ! $me instanceof Authenticatable) {
            return;
        }

        try {
            app(Messages::class)->send($conversation, $me, $this->body);
        } catch (RateLimited $e) {
            $this->addError('body', __('filum::filum.composer.rate_limited', ['seconds' => $e->retryAfter]));

            return;
        } catch (InvalidArgumentException) {
            $this->addError('body', __('filum::filum.composer.empty'));

            return;
        }

        $this->clearComposer();
    }

    /**
     * Empty the composer, on the server and in the browser.
     *
     * The textarea is deliberately not re-rendered by Livewire -- see the comment
     * in the composer partial -- so emptying the property is not enough on its
     * own: the browser has to be told, and only when a send actually succeeded.
     */
    private function clearComposer(): void
    {
        $this->body = '';
        $this->resetErrorBag();
        $this->dispatch('filum-composer-cleared');
    }

    /**
     * Reach one page further back, keeping everything already on screen.
     */
    public function loadOlder(): void
    {
        $conversation = $this->conversation();
        $thread = $this->thread();

        if (! $conversation instanceof Conversation || $thread->isEmpty()) {
            return;
        }

        $floor = app(Messages::class)->floorBefore($conversation, $thread->first()->id);

        if ($floor !== null) {
            $this->from = $floor;
        }
    }

    /**
     * Whether to offer earlier messages: only when there are earlier messages.
     *
     * @param  Collection<int, Message>  $thread
     */
    private function hasOlder(Collection $thread): bool
    {
        $conversation = $this->conversation();

        if (! $conversation instanceof Conversation || $thread->isEmpty()) {
            return false;
        }

        return app(Messages::class)->hasOlderThan($conversation, $thread->first()->id);
    }

    /**
     * The periodic tick: record presence, and let the render pick up anything new.
     *
     * This is the same call under both drivers -- fast under polling, slow under
     * a broadcaster, where it exists to heal a dropped socket rather than to
     * deliver.
     *
     * A tick that finds nothing new renders nothing. That is not an optimisation:
     * every render morphs the DOM, and a morph while somebody is mid-sentence
     * costs them the caret in the box they are typing in. Since most ticks in a
     * quiet chat find nothing, most ticks must leave the page alone.
     */
    public function tick(): void
    {
        $user = $this->user();

        if ($user instanceof Authenticatable) {
            app(Heartbeat::class)->beat($user);
        }

        $conversation = $this->conversation();

        if ($conversation instanceof Conversation && $user instanceof Authenticatable) {
            app(Messages::class)->markRead($conversation, $user);
        }

        if ($this->fingerprint() === $this->seen) {
            $this->skipRender();
        }
    }

    /**
     * Everything a render depends on, in one comparable string: the newest message
     * in the open conversation, how much is unread anywhere, and who is around.
     *
     * Deliberately not the rendered HTML -- hashing markup would make every
     * relative timestamp a change, and the thing would never settle.
     */
    private function fingerprint(): string
    {
        $user = $this->user();

        if (! $user instanceof Authenticatable) {
            return '';
        }

        $conversation = $this->conversation();

        return implode('|', [
            $conversation instanceof Conversation
                ? (string) Message::query()->where('conversation_id', $conversation->id)->max('id')
                : '',
            (string) app(Messages::class)->unreadTotal($user),
            implode(',', array_map(strval(...), app(PresenceStore::class)->active())),
            // Without this a quiet tick would skip rendering and a new invitation
            // would not appear until something else changed -- the skip-render
            // that protects the composer would swallow the one thing invitations
            // exist to deliver.
            (string) app(Groups::class)->invitationsFor($user)->count(),
        ]);
    }

    /**
     * The messages on screen.
     *
     * @return Collection<int, Message>
     */
    private function thread(): Collection
    {
        $conversation = $this->conversation();

        if (! $conversation instanceof Conversation) {
            return collect();
        }

        return app(Messages::class)->page($conversation, $this->from);
    }

    /**
     * The other person in a direct conversation. A group has none.
     *
     * Read off the participants rather than off a selected user id, because
     * there is no longer a selected user: the conversation is what is open.
     */
    private function partner(): ?Authenticatable
    {
        $conversation = $this->conversation();
        $me = $this->user();

        if (! $conversation instanceof Conversation || $conversation->isGroup() || ! $me instanceof Authenticatable) {
            return null;
        }

        $users = app(UserProvider::class);
        $otherId = $conversation->participants()
            ->whereNot('user_id', $users->id($me))
            ->value('user_id');

        return $otherId === null ? null : $users->find((string) $otherId);
    }

    /**
     * The open conversation, re-read each call so a deleted group falls away --
     * and only if the viewer is actually in it.
     *
     * $conversation is a public Livewire property, so the browser can set it to any
     * integer it likes. Existence is not permission: without the membership check
     * a forged id would render somebody else's thread, message bodies and all,
     * into the response. Guarding it here rather than at each caller is the point,
     * because every read goes through this one seam -- thread(), hasOlder(),
     * partner(), render()'s group, send(), received(), tick(), the fingerprint and
     * all three owner actions -- and a seam is the only place a check cannot be
     * forgotten.
     *
     * selectConversation() keeps its own identical check: that one stops a bad id
     * from ever being stored, this one stops a stored bad id from being read, and
     * neither depends on the other being right.
     *
     * Null rather than an exception, like everything else here: a stale id from an
     * old page is turned away in silence.
     */
    private function conversation(): ?Conversation
    {
        if ($this->conversation === null) {
            return null;
        }

        if ($this->memoisedFor === $this->conversation) {
            return $this->memoised;
        }

        $this->memoisedFor = $this->conversation;

        return $this->memoised = $this->resolve($this->conversation);
    }

    /**
     * The uncached answer: does this id name a conversation this viewer is in?
     */
    private function resolve(int $id): ?Conversation
    {
        $me = $this->user();
        $conversation = Conversation::query()->find($id);

        if (! $conversation instanceof Conversation || ! $me instanceof Authenticatable) {
            return null;
        }

        // A group nobody is meant to be able to reach. Membership survives the
        // switch, so without this a joined member could still read the thread, send
        // into it and broadcast from it while the configuration says groups do not
        // exist -- and could not even leave, because leaving is refused. Absent,
        // like everywhere else in Filum.
        if ($conversation->isGroup() && ! app(Groups::class)->enabled()) {
            return null;
        }

        // Joined, not merely present as a row: the same narrowing Conversation
        // ::includes applies for broadcast authorization and the send path, so a
        // pending invitee cannot read a thread they have not accepted either.
        return $conversation->includes(app(UserProvider::class)->id($me)) ? $conversation : null;
    }

    /**
     * Drop the memo, so the next read goes back to the database.
     */
    private function forget(): void
    {
        $this->memoisedFor = null;
        $this->memoised = null;
    }

    private function user(): ?Authenticatable
    {
        $user = Filum::user();

        return Filum::authorized($user) ? $user : null;
    }

    /**
     * Pick up a message that arrived over a socket.
     *
     * Called from the browser when a broadcast lands, and it deliberately does no
     * more than re-render: the payload carries ids, so the thread is re-read
     * through the same authorized query the page always uses.
     */
    public function received(): void
    {
        $user = $this->user();
        $conversation = $this->conversation();

        if ($conversation instanceof Conversation && $user instanceof Authenticatable) {
            app(Messages::class)->markRead($conversation, $user);
        }
    }
}
