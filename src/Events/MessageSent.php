<?php

declare(strict_types=1);

namespace Heyosseus\Filum\Events;

use Heyosseus\Filum\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Message $message) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('filum.conversation.'.$this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'filum.message.sent';
    }

    /**
     * The payload deliberately carries an id rather than a body: a subscriber
     * re-reads through the same authorized path the page uses, so a message can
     * never arrive at someone the query would not have shown it to.
     *
     * @return array{id: int, conversation_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
        ];
    }
}
