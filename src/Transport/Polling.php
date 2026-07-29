<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Transport;

use Heyosseus\Filum\Contracts\Transport;
use Heyosseus\Filum\Models\Message;
use Illuminate\Contracts\Config\Repository;

/**
 * The driver for an application with no broadcaster.
 *
 * Announcing does nothing on purpose: under polling the client discovers new
 * messages by asking, so there is nowhere to push them. Everything that makes
 * the chat work -- persistence, ordering, read state -- has already happened by
 * the time a transport is consulted.
 */
final readonly class Polling implements Transport
{
    public function __construct(private Repository $config) {}

    public function messageSent(Message $message): void
    {
        //
    }

    public function presenceChanged(int|string $userId, bool $online): void
    {
        //
    }

    public function descriptor(): array
    {
        $interval = $this->config->get('filum.transport.poll_interval', 5);

        return [
            'driver' => 'polling',
            'poll' => is_int($interval) && $interval > 0 ? $interval : 5,
        ];
    }
}
