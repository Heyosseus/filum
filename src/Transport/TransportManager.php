<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Transport;

use Heyosseus\Filum\Contracts\Transport;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/**
 * Which way this application delivers.
 *
 * The "auto" default is what lets Filum work the moment it is required: an
 * application that has never configured broadcasting gets polling and a working
 * chat, and one that has gets real-time without configuring anything twice.
 */
final readonly class TransportManager
{
    /**
     * Broadcast connections that exist but do not deliver anywhere.
     *
     * @var list<string>
     */
    private const array INERT = ['null', 'log'];

    public function __construct(
        private Container $container,
        private Repository $config,
    ) {}

    public function driver(): Transport
    {
        return $this->broadcasts()
            ? $this->container->make(LaravelBroadcast::class)
            : $this->container->make(Polling::class);
    }

    /**
     * The name of the driver that would be chosen, without building it.
     *
     * @return 'broadcast'|'polling'
     */
    public function name(): string
    {
        return $this->broadcasts() ? 'broadcast' : 'polling';
    }

    private function broadcasts(): bool
    {
        $configured = $this->config->get('filum.transport.driver', 'auto');

        if ($configured === 'broadcast') {
            return true;
        }

        if ($configured === 'polling') {
            return false;
        }

        return $this->hasRealBroadcaster();
    }

    /**
     * Whether the application's own broadcasting config points somewhere real.
     */
    private function hasRealBroadcaster(): bool
    {
        $connection = $this->config->get('broadcasting.default');

        if (! is_string($connection) || $connection === '') {
            return false;
        }

        if (in_array($connection, self::INERT, true)) {
            return false;
        }

        // A connection named in config but never defined is not a broadcaster.
        return is_array($this->config->get('broadcasting.connections.'.$connection));
    }
}
