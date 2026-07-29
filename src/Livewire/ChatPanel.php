<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Livewire;

use Heyosseus\Filum\Contracts\PresenceStore;
use Heyosseus\Filum\Contracts\Transport;
use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Conversations\ConversationKey;
use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Exceptions\RateLimited;
use Heyosseus\Filum\Filum;
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

    /** The colleague whose conversation is open, as a string key. */
    public ?string $selected = null;

    /** Whether the overlay is expanded. Ignored in page mode. */
    public bool $open = false;

    public string $body = '';

    public string $search = '';

    /** The oldest message on screen, for keyset scrollback. */
    public ?int $oldest = null;

    public function mount(string $mode = 'page'): void
    {
        $this->mode = $mode === 'overlay' ? 'overlay' : 'page';
    }

    public function render(): View
    {
        $user = $this->user();

        // Resolved through the factory rather than the view() helper: a package's
        // namespaced views are registered at runtime, so static analysis cannot
        // know 'filum::…' is a real view-string, and the factory takes a plain one.
        return app(Factory::class)->make('filum::livewire.chat-panel', [
            'me' => $user,
            'colleagues' => $user instanceof Authenticatable ? $this->colleagues($user) : collect(),
            'thread' => $this->thread(),
            'partner' => $this->partner(),
            'poll' => $this->pollInterval(),
        ]);
    }

    /**
     * Open a conversation with a colleague.
     */
    public function selectUser(string $id): void
    {
        $this->selected = $id;
        $this->oldest = null;
        $this->body = '';
        $this->resetErrorBag();

        $conversation = $this->conversation();
        $me = $this->user();

        if ($conversation instanceof Conversation && $me instanceof Authenticatable) {
            app(Messages::class)->markRead($conversation, $me);
        }
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
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

        $this->body = '';
    }

    /**
     * Walk further back through the thread.
     */
    public function loadOlder(): void
    {
        $thread = $this->thread();

        if ($thread->isNotEmpty()) {
            $this->oldest = $thread->first()->id;
        }
    }

    /**
     * The periodic tick: record presence, and let the render pick up anything new.
     *
     * This is the same call under both drivers -- fast under polling, slow under
     * a broadcaster, where it exists to heal a dropped socket rather than to
     * deliver.
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
    }

    /**
     * The colleagues to list, with presence and unread counts attached.
     *
     * @return Collection<int, array{id: string, name: string, avatar: string|null, online: bool, unread: int}>
     */
    private function colleagues(Authenticatable $me): Collection
    {
        $users = app(UserProvider::class);
        $active = app(PresenceStore::class)->active();
        $messages = app(Messages::class);

        $search = mb_strtolower(trim($this->search));

        return $users->chattable($me)
            ->map(function (Authenticatable $colleague) use ($users, $active, $messages, $me): array {
                $id = $users->id($colleague);

                // Reading the unread count needs the conversation, but listing a
                // colleague must never create one -- otherwise merely opening the
                // sidebar would write a row per person.
                $key = ConversationKey::for([$users->id($me), $id]);
                $conversation = Conversation::query()->where('key', $key)->first();

                return [
                    'id' => (string) $id,
                    'name' => $users->name($colleague),
                    'avatar' => $users->avatar($colleague),
                    'online' => in_array($id, $active, true),
                    'unread' => $conversation instanceof Conversation
                        ? $messages->unreadIn($conversation, $me)
                        : 0,
                ];
            })
            ->filter(fn (array $row): bool => $search === '' || str_contains(mb_strtolower($row['name']), $search))
            ->values();
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

        return app(Messages::class)->page($conversation, $this->oldest);
    }

    private function partner(): ?Authenticatable
    {
        return $this->selected === null ? null : app(UserProvider::class)->find($this->selected);
    }

    /**
     * The conversation with the selected colleague, created on first open.
     */
    private function conversation(): ?Conversation
    {
        $me = $this->user();
        $partner = $this->partner();

        if (! $me instanceof Authenticatable || ! $partner instanceof Authenticatable) {
            return null;
        }

        $users = app(UserProvider::class);

        return app(Conversations::class)->between($users->id($me), $users->id($partner));
    }

    private function user(): ?Authenticatable
    {
        $user = Filum::user();

        return Filum::authorized($user) ? $user : null;
    }

    private function pollInterval(): int
    {
        return app(Transport::class)->descriptor()['poll'];
    }
}
