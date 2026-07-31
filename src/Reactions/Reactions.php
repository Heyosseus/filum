<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Reactions;

use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Exceptions\NotAParticipant;
use Heyosseus\Filum\Exceptions\UnknownEmoji;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Models\Reaction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;

/**
 * Emoji reactions on a message.
 *
 * A reaction is a toggle rather than a create: the same person tapping the same
 * emoji twice means they changed their mind, which is the only sensible reading
 * and the only one that needs no separate "remove" affordance.
 *
 * The set of emoji is configuration, not a picker. Filum ships compiled CSS and
 * no build step, so a full picker would mean a JavaScript bundle for something a
 * back office uses six of; naming the six in config is smaller in every way and
 * lets an application choose its own.
 */
final readonly class Reactions
{
    /** @var list<string> */
    private const array FALLBACK = ['👍', '❤️', '😂', '🎉', '👀', '✅'];

    public function __construct(
        private UserProvider $users,
        private Repository $config,
    ) {}

    /**
     * Add the reaction, or take it away if it is already there.
     *
     * @throws NotAParticipant when the reactor is not in the conversation.
     * @throws UnknownEmoji when the emoji is not one of the configured set.
     */
    public function toggle(Message $message, Authenticatable $user, string $emoji): void
    {
        if (! in_array($emoji, $this->emoji(), true)) {
            throw UnknownEmoji::of($emoji);
        }

        $conversation = $message->conversation;
        $userId = $this->users->id($user);

        // Reacting is reading plus writing, so it answers to the same membership
        // question the thread does -- and the id reaches this from the browser.
        if (! $conversation instanceof Conversation || ! $conversation->includes($userId)) {
            throw NotAParticipant::of($message->conversation_id);
        }

        $existing = Reaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing instanceof Reaction) {
            $existing->delete();

            return;
        }

        Reaction::query()->create([
            'message_id' => $message->id,
            'user_id' => $userId,
            'emoji' => $emoji,
        ]);
    }

    /**
     * Reactions for a whole thread, keyed by message id.
     *
     * Read in one query for the whole page rather than one per message: a
     * fifty-message thread should cost one round trip, not fifty.
     *
     * @param  Collection<int, Message>  $messages
     * @return array<int, list<array{emoji: string, count: int, mine: bool}>>
     */
    public function forThread(Collection $messages, Authenticatable $user): array
    {
        $ids = $messages->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $mine = (string) $this->users->id($user);
        $grouped = [];

        foreach (Reaction::query()->whereIn('message_id', $ids)->get() as $reaction) {
            $key = $reaction->message_id;
            $emoji = $reaction->emoji;

            $grouped[$key][$emoji] ??= ['emoji' => $emoji, 'count' => 0, 'mine' => false];
            $grouped[$key][$emoji]['count']++;

            if ((string) $reaction->user_id === $mine) {
                $grouped[$key][$emoji]['mine'] = true;
            }
        }

        // Ordered by the configured set so a message's reactions do not reshuffle
        // as counts change -- a row of chips that moves under the cursor is worse
        // than one that is merely unsorted.
        $order = array_flip($this->emoji());

        return array_map(
            function (array $reactions) use ($order): array {
                uasort($reactions, fn (array $a, array $b): int => ($order[$a['emoji']] ?? PHP_INT_MAX) <=> ($order[$b['emoji']] ?? PHP_INT_MAX));

                return array_values($reactions);
            },
            $grouped,
        );
    }

    /**
     * The emoji an application offers. Anything not in here is refused.
     *
     * @return list<string>
     */
    public function emoji(): array
    {
        if ($this->config->get('filum.reactions.enabled', true) !== true) {
            return [];
        }

        $configured = $this->config->get('filum.reactions.emoji');

        if (! is_array($configured) || $configured === []) {
            return self::FALLBACK;
        }

        return array_values(array_filter($configured, is_string(...)));
    }
}
