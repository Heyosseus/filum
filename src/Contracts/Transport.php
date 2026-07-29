<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Contracts;

use Heyosseus\Filum\Models\Message;

/**
 * How a message or a presence change reaches the other people looking at Filum.
 *
 * The surface is deliberately narrow. Everything the UI needs to know about the
 * difference between a WebSocket and a poll is in descriptor(); everything the
 * send path needs to know is that announcing may quietly do nothing.
 */
interface Transport
{
    /**
     * Announce a message that has already been persisted.
     *
     * Implementations must not throw: the message is saved by the time this is
     * called, and losing it to a broadcaster outage would be worse than a short
     * delay before it appears elsewhere.
     */
    public function messageSent(Message $message): void;

    /**
     * Announce that a user came online or went away.
     */
    public function presenceChanged(int|string $userId, bool $online): void;

    /**
     * What the client should do to stay current.
     *
     * @return array{driver: 'broadcast'|'polling', poll: int}
     */
    public function descriptor(): array;
}
