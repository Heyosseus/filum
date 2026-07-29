<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Transport;

use Heyosseus\Filum\Contracts\Transport;
use Heyosseus\Filum\Events\MessageSent;
use Heyosseus\Filum\Events\PresenceChanged;
use Heyosseus\Filum\Models\Message;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The driver for an application that has a broadcaster -- any broadcaster.
 *
 * Filum names no vendor. Whatever is configured in config/broadcasting.php
 * carries these events: Reverb, soketi, Pusher, Ably. Nothing here requires a
 * paid service, and the quickstart uses Reverb.
 *
 * Failures are swallowed and logged rather than raised. By the time a transport
 * is consulted the message is already saved; letting a WebSocket outage bubble
 * up would turn a delivery delay into a lost message and a broken send button.
 */
final readonly class LaravelBroadcast implements Transport
{
    public function __construct(
        private Dispatcher $events,
        private Repository $config,
        private LoggerInterface $logger,
    ) {}

    public function messageSent(Message $message): void
    {
        $this->announce(new MessageSent($message), 'message');
    }

    public function presenceChanged(int|string $userId, bool $online): void
    {
        $this->announce(new PresenceChanged($userId, $online), 'presence');
    }

    public function descriptor(): array
    {
        $interval = $this->config->get('filum.transport.reconcile_interval', 30);

        // The slow reconciliation poll stays on under a broadcaster, so a dropped
        // socket or a laptop waking from sleep heals itself instead of leaving
        // someone reading a stale thread.
        return [
            'driver' => 'broadcast',
            'poll' => is_int($interval) && $interval > 0 ? $interval : 30,
        ];
    }

    private function announce(object $event, string $kind): void
    {
        try {
            $this->events->dispatch($event);
        } catch (Throwable $e) {
            $this->logger->warning('Filum could not broadcast a '.$kind.' event.', [
                'exception' => $e,
            ]);
        }
    }
}
