<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Messages;

use Carbon\CarbonImmutable;
use Heyosseus\Filum\Contracts\Transport;
use Heyosseus\Filum\Contracts\UserProvider;
use Heyosseus\Filum\Conversations\Conversations;
use Heyosseus\Filum\Exceptions\NotAParticipant;
use Heyosseus\Filum\Exceptions\RateLimited;
use Heyosseus\Filum\Models\Conversation;
use Heyosseus\Filum\Models\Message;
use Heyosseus\Filum\Models\Participant;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class Messages
{
    public function __construct(
        private Conversations $conversations,
        private UserProvider $users,
        private Transport $transport,
        private RateLimiter $limiter,
        private Repository $config,
        private LoggerInterface $logger,
    ) {}

    /**
     * Persist a message, then announce it.
     *
     * The order matters. Writing first and announcing second means a broadcaster
     * outage costs a delay, not a message -- and the announcement is allowed to
     * fail quietly because the reconciliation poll will find what it missed.
     *
     * @throws NotAParticipant when the sender is not in the conversation.
     * @throws RateLimited when the sender is sending too fast.
     */
    public function send(Conversation $conversation, Authenticatable $sender, string $body): Message
    {
        $senderId = $this->users->id($sender);

        if (! $conversation->includes($senderId)) {
            throw NotAParticipant::of($conversation->id);
        }

        $body = $this->clean($body);

        $this->rateLimit($senderId);

        $message = DB::transaction(function () use ($conversation, $senderId, $body): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $senderId,
                'body' => $body,
            ]);

            $conversation->forceFill(['last_message_at' => CarbonImmutable::now()])->save();

            // The sender has by definition read what they just wrote.
            Participant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $senderId)
                ->update(['last_read_at' => CarbonImmutable::now()]);

            return $message;
        });

        $this->announce($message);

        return $message;
    }

    /**
     * Announce a saved message, never letting the attempt undo the saving.
     *
     * The bundled broadcast driver already catches its own failures, but
     * Transport is a public contract: an application may implement it against
     * something of its own, and a bug in that implementation must not turn into
     * a send button that appears broken.
     */
    private function announce(Message $message): void
    {
        try {
            $this->transport->messageSent($message);
        } catch (Throwable $e) {
            $this->logger->warning('Filum could not announce a sent message.', ['exception' => $e]);
        }
    }

    /**
     * A page of messages, oldest first, ending at the newest.
     *
     * Passing the id of the oldest message already on screen walks backwards
     * through the thread by keyset rather than by offset, so scrollback stays
     * cheap however long the conversation gets.
     *
     * @return Collection<int, Message>
     */
    public function page(Conversation $conversation, ?int $before = null): Collection
    {
        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit($this->perPage());

        if ($before !== null) {
            $query->where('id', '<', $before);
        }

        /** @var Collection<int, Message> $messages */
        $messages = $query->get()->reverse()->values();

        return $messages;
    }

    /**
     * Messages in a conversation newer than the given id, oldest first.
     *
     * This is what both drivers reconcile with -- the fast poll under polling,
     * the slow one under a broadcaster.
     *
     * @return Collection<int, Message>
     */
    public function since(Conversation $conversation, int $after): Collection
    {
        /** @var Collection<int, Message> $messages */
        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get();

        return $messages;
    }

    public function markRead(Conversation $conversation, Authenticatable $user): void
    {
        $participant = $this->conversations->participant($conversation, $this->users->id($user));

        $participant?->forceFill(['last_read_at' => CarbonImmutable::now()])->save();
    }

    /**
     * How many messages in this conversation the user has not seen.
     */
    public function unreadIn(Conversation $conversation, Authenticatable $user): int
    {
        $userId = $this->users->id($user);
        $participant = $this->conversations->participant($conversation, $userId);

        if (! $participant instanceof Participant) {
            return 0;
        }

        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $userId);

        if ($participant->last_read_at !== null) {
            $query->where('created_at', '>', $participant->last_read_at);
        }

        return $query->count();
    }

    /**
     * How many unseen messages the user has across every conversation.
     */
    public function unreadTotal(Authenticatable $user): int
    {
        $userId = $this->users->id($user);

        $total = 0;

        Participant::query()
            ->where('user_id', $userId)
            ->get()
            ->each(function (Participant $participant) use (&$total, $userId): void {
                $query = Message::query()
                    ->where('conversation_id', $participant->conversation_id)
                    ->where('sender_id', '!=', $userId);

                if ($participant->last_read_at !== null) {
                    $query->where('created_at', '>', $participant->last_read_at);
                }

                $total += $query->count();
            });

        return $total;
    }

    /**
     * Trim and bound the body. An empty message is not a message.
     */
    private function clean(string $body): string
    {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('A message cannot be empty.');
        }

        $max = $this->config->get('filum.messages.max_length', 2000);
        $max = is_int($max) && $max > 0 ? $max : 2000;

        return mb_substr($body, 0, $max);
    }

    /**
     * @throws RateLimited
     */
    private function rateLimit(int|string $senderId): void
    {
        $limit = $this->config->get('filum.messages.rate_limit', 30);
        $window = $this->config->get('filum.messages.rate_window', 60);

        $limit = is_int($limit) ? $limit : 30;
        $window = is_int($window) && $window > 0 ? $window : 60;

        if ($limit <= 0) {
            return;
        }

        $key = 'filum:send:'.$senderId;

        if ($this->limiter->tooManyAttempts($key, $limit)) {
            throw new RateLimited($this->limiter->availableIn($key));
        }

        $this->limiter->hit($key, $window);
    }

    private function perPage(): int
    {
        $perPage = $this->config->get('filum.messages.per_page', 50);

        return is_int($perPage) && $perPage > 0 ? $perPage : 50;
    }
}
