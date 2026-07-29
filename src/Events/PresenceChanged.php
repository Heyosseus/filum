<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final class PresenceChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly int|string $userId,
        public readonly bool $online,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('filum.presence')];
    }

    public function broadcastAs(): string
    {
        return 'filum.presence.changed';
    }

    /**
     * Carries no presence state of its own -- it tells subscribers to re-read the
     * store, which remains the single source of truth.
     *
     * @return array{user_id: int|string, online: bool}
     */
    public function broadcastWith(): array
    {
        return ['user_id' => $this->userId, 'online' => $this->online];
    }
}
